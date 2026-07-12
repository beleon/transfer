<?php
namespace OCA\Transfer\Service;

use OCA\Transfer\Activity\Providers\TransferFailedProvider;
use OCA\Transfer\Activity\Providers\TransferStartedProvider;
use OCA\Transfer\Activity\Providers\TransferSucceededProvider;

use OCP\Activity\IManager;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\Http\Client\IClientService;
use OCP\Http\Client\LocalServerException;
use OCP\ICacheFactory;
use OCP\IConfig;
use OCP\ITempManager;

class TransferService {
	public const RESULT_SUCCESS = 'success';
	public const RESULT_FAILED = 'failed';
	public const RESULT_CANCELLED = 'cancelled';

	protected $activityManager;
	protected $clientService;
	protected $rootFolder;
	protected $tempManager;
	protected $cacheFactory;
	protected $config;

	public function __construct(
		IManager $activityManager,
		IClientService $clientService,
		IRootFolder $rootFolder,
		ITempManager $tempManager,
		ICacheFactory $cacheFactory,
		IConfig $config
	) {
		$this->activityManager = $activityManager;
		$this->clientService = $clientService;
		$this->rootFolder = $rootFolder;
		$this->tempManager = $tempManager;
		$this->cacheFactory = $cacheFactory;
		$this->config = $config;
	}

	public function isProgressAvailable(): bool {
		return $this->config->getSystemValue('memcache.distributed', '') !== '';
	}

	/**
	 * @return string One of the RESULT_* constants.
	 */
	public function transfer(string $userId, string $path, string $url, string $hashAlgo, string $hash, string $transferId) {
		$hash = strtolower(trim($hash));
		$userFolder = $this->rootFolder->getUserFolder($userId);

		$this->generateStartedEvent($userId, $path, $url);

		$tmpPath = $this->tempManager->getTemporaryFile();

		$client = $this->clientService->newClient();
		$cache = $this->isProgressAvailable() ? $this->cacheFactory->createDistributed('transfer') : null;
		$lastUpdate = 0;
		$cancelled = false;

		// Register this transfer in the user's index. The TTL is generous:
		// entries are removed on completion, and stale ones are skipped by
		// the progress endpoint once their progress key expires.
		if ($cache) {
			$index = json_decode($cache->get('index:' . $userId) ?: '[]', true);
			$index[] = $transferId;
			$cache->set('index:' . $userId, json_encode($index), 86400);
		}

		try {
			$options = [
				"sink" => $tmpPath,
				"timeout" => 0,
				// Abort when the connection stalls (under 1 byte/s for 120s).
				// Guzzle's read_timeout only applies to its stream handler,
				// so use the curl options directly.
				"curl" => [
					CURLOPT_LOW_SPEED_LIMIT => 1,
					CURLOPT_LOW_SPEED_TIME => 120,
				],
			];
			if ($cache) {
				// Use raw curl progress function — returning non-zero aborts the transfer
				$options["curl"][CURLOPT_NOPROGRESS] = false;
				$options["curl"][CURLOPT_PROGRESSFUNCTION] = function ($resource, $downloadTotal, $downloaded, $uploadTotal, $uploaded) use ($cache, $transferId, $path, $url, &$lastUpdate, &$cancelled) {
					$now = time();
					if ($now - $lastUpdate < 2) {
						return 0;
					}
					$lastUpdate = $now;

					// Check for explicit cancellation
					$shouldCancel = (bool)$cache->get('cancel:' . $transferId);

					// Check heartbeat (only exists for immediate transfers)
					if (!$shouldCancel) {
						$heartbeat = $cache->get('heartbeat:' . $transferId);
						if ($heartbeat !== null && ($now - (int)$heartbeat) > 10) {
							$shouldCancel = true;
						}
					}

					if ($shouldCancel) {
						$cancelled = true;
						$cache->remove('cancel:' . $transferId);
						$cache->remove('progress:' . $transferId);
						return 1; // Abort curl
					}

					$cache->set('progress:' . $transferId, json_encode([
						"id" => $transferId,
						"filename" => basename($path),
						"url" => $url,
						"total" => $downloadTotal,
						"downloaded" => $downloaded,
						"updated" => $now,
					]), 3600);

					return 0;
				};
			}
			$response = $client->get($url, $options);
		} catch (\Exception $exception) {
			if ($cache) $this->removeTransfer($cache, $userId, $transferId);
			@unlink($tmpPath);
			if ($cancelled) {
				$this->generateFailedEvent($userId, $path, $url);
				return self::RESULT_CANCELLED;
			}
			if ($exception instanceof LocalServerException) {
				$this->generateBlockedEvent($userId, $path, $url);
			} else {
				$this->generateFailedEvent($userId, $path, $url);
			}
			return self::RESULT_FAILED;
		}

		if ($cache) $this->removeTransfer($cache, $userId, $transferId);

		if ($hash === "" || hash_equals(hash_file($hashAlgo, $tmpPath), $hash)) {
			$dirPath = dirname($path);
			$filename = basename($path);

			try {
				$dir = $userFolder->get($dirPath);
			} catch (NotFoundException $e) {
				unlink($tmpPath);
				$this->generateFailedEvent($userId, $path, $url);
				return self::RESULT_FAILED;
			}

			// Retry loop in case of concurrent writes with the same filename
			for ($attempt = 0; $attempt < 5; $attempt++) {
				$file = null;
				try {
					$uniqueName = $dir->getNonExistingName($filename);
					$file = $dir->newFile($uniqueName);
					$file->putContent(fopen($tmpPath, 'r'));
					break;
				} catch (\Exception $e) {
					// Don't leave a partial file behind before retrying
					if ($file !== null) {
						try {
							$file->delete();
						} catch (\Exception $ignored) {
						}
					}
					if ($attempt === 4) {
						unlink($tmpPath);
						$this->generateFailedEvent($userId, $path, $url);
						return self::RESULT_FAILED;
					}
					usleep(100000); // 100ms before retry
				}
			}
			unlink($tmpPath);

			$actualPath = $dirPath . '/' . $uniqueName;
			$this->generateSucceededEvent($userId, $actualPath, $url, $file->getId());
			return self::RESULT_SUCCESS;
		} else {
			unlink($tmpPath);

			$this->generateHashFailedEvent($userId, $path, $url);
			return self::RESULT_FAILED;
		}
	}

	protected function removeTransfer($cache, string $userId, string $transferId) {
		$cache->remove('progress:' . $transferId);
		$cache->remove('heartbeat:' . $transferId);
		$index = json_decode($cache->get('index:' . $userId) ?: '[]', true);
		$index = array_values(array_filter($index, function ($id) use ($transferId) {
			return $id !== $transferId;
		}));
		if (empty($index)) {
			$cache->remove('index:' . $userId);
		} else {
			$cache->set('index:' . $userId, json_encode($index), 86400);
		}
	}

	protected function generateStartedEvent(string $userId, string $path, string $url) {
		$event = $this->activityManager->generateEvent();
		$event->setApp("transfer");
		$event->setType(TransferStartedProvider::TYPE_TRANSFER_STARTED);
		$event->setAffectedUser($userId);
		$event->setSubject(TransferStartedProvider::SUBJECT_TRANSFER_STARTED, ["url" => $url]);
		$this->activityManager->publish($event);
	}

	protected function generateFailedEvent(string $userId, string $path, string $url) {
		$event = $this->activityManager->generateEvent();
		$event->setApp("transfer");
		$event->setType(TransferFailedProvider::TYPE_TRANSFER_FAILED);
		$event->setAffectedUser($userId);
		$event->setSubject(TransferFailedProvider::SUBJECT_TRANSFER_FAILED, ["url" => $url]);
		$this->activityManager->publish($event);
	}

	protected function generateHashFailedEvent(string $userId, string $path, string $url) {
		$event = $this->activityManager->generateEvent();
		$event->setApp("transfer");
		$event->setType(TransferFailedProvider::TYPE_TRANSFER_FAILED);
		$event->setAffectedUser($userId);
		$event->setSubject(TransferFailedProvider::SUBJECT_TRANSFER_HASH_FAILED, ["url" => $url]);
		$this->activityManager->publish($event);
	}

	protected function generateBlockedEvent(string $userId, string $path, string $url) {
		$event = $this->activityManager->generateEvent();
		$event->setApp("transfer");
		$event->setType(TransferFailedProvider::TYPE_TRANSFER_FAILED);
		$event->setAffectedUser($userId);
		$event->setSubject(TransferFailedProvider::SUBJECT_TRANSFER_BLOCKED, ["url" => $url]);
		$this->activityManager->publish($event);
	}

	protected function generateSucceededEvent(string $userId, string $path, string $url, int $fileId) {
		$event = $this->activityManager->generateEvent();
		$event->setApp("transfer");
		$event->setType(TransferSucceededProvider::TYPE_TRANSFER_SUCCEEDED);
		$event->setAffectedUser($userId);
		$event->setSubject(TransferSucceededProvider::SUBJECT_TRANSFER_SUCCEEDED, ["url" => $url]);
		$event->setObject("files", $fileId, $path);
		$this->activityManager->publish($event);
	}
}
