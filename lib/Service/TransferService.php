<?php
namespace OCA\Transfer\Service;

use OCA\Transfer\Activity\Providers\TransferFailedProvider;
use OCA\Transfer\Activity\Providers\TransferStartedProvider;
use OCA\Transfer\Activity\Providers\TransferSucceededProvider;

use GuzzleHttp\Exception\BadResponseException;
use OCP\Activity\IManager;
use OCP\Files\IRootFolder;
use OCP\Http\Client\IClientService;
use OCP\Http\Client\LocalServerException;

class TransferService {
	protected $activityManager;
	protected $clientService;
	protected $rootFolder;

	public function __construct(
		IManager $activityManager,
		IClientService $clientService,
		IRootFolder $rootFolder
	) {
		$this->activityManager = $activityManager;
		$this->clientService = $clientService;
		$this->rootFolder = $rootFolder;
	}

	/**
	 * @return Whether the transfer succeeded.
	 */
	public function transfer(string $userId, string $path, string $url, string $hashAlgo, string $hash) {
		$userFolder = $this->rootFolder->getUserFolder($userId);

		$this->generateStartedEvent($userId, $path, $url);

		$tmpPath = tempnam(sys_get_temp_dir(), "nextcloud-transfer-");

		$client = $this->clientService->newClient();

		try {
			$response = $client->get($url, ["sink" => $tmpPath, "timeout" => 0]);
		} catch (BadResponseException $exception) {
			$this->generateFailedEvent($userId, $path, $url);
			return false;
		} catch (LocalServerException $exception) {
			$this->generateBlockedEvent($userId, $path, $url);
			return false;
		}

		if ($hash == "" || hash_file($hashAlgo, $tmpPath) == $hash) {
			$dirPath = dirname($path);
			$filename = basename($path);
			$dir = $userFolder->get($dirPath);
			$file = $dir->newFile($filename);
			$file->putContent(fopen($tmpPath, 'r'));
			unlink($tmpPath);

			$this->generateSucceededEvent($userId, $path, $url, $file->getId());
			return true;
		} else {
			unlink($tmpPath);

			$this->generateHashFailedEvent($userId, $path, $url);
			return false;
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
