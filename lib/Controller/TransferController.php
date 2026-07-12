<?php
namespace OCA\Transfer\Controller;

use OCP\BackgroundJob\IJobList;
use OCP\Http\Client\IClientService;
use OCP\Http\Client\LocalServerException;
use OCP\ICacheFactory;
use OCP\IRequest;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;

use OCA\Transfer\BackgroundJob\TransferJob;
use OCA\Transfer\Service\TransferService;

class TransferController extends Controller {
	private $userId;
	private $jobList;
	private $clientService;
	private $cacheFactory;
	private $service;

	private const MIME_EXTENSIONS = [
		'image/jpeg' => 'jpg',
		'image/png' => 'png',
		'image/gif' => 'gif',
		'image/webp' => 'webp',
		'image/svg+xml' => 'svg',
		'image/bmp' => 'bmp',
		'image/tiff' => 'tiff',
		'application/pdf' => 'pdf',
		'application/zip' => 'zip',
		'application/gzip' => 'gz',
		'application/x-tar' => 'tar',
		'application/x-bzip2' => 'bz2',
		'application/x-xz' => 'xz',
		'application/x-7z-compressed' => '7z',
		'application/x-rar-compressed' => 'rar',
		'application/json' => 'json',
		'application/xml' => 'xml',
		'application/javascript' => 'js',
		'text/html' => 'html',
		'text/plain' => 'txt',
		'text/css' => 'css',
		'text/csv' => 'csv',
		'audio/mpeg' => 'mp3',
		'audio/ogg' => 'ogg',
		'audio/flac' => 'flac',
		'audio/wav' => 'wav',
		'video/mp4' => 'mp4',
		'video/webm' => 'webm',
		'video/x-matroska' => 'mkv',
		'application/ogg' => 'ogx',
	];

	public function __construct(
		$AppName,
		IRequest $request,
		IJobList $jobList,
		IClientService $clientService,
		ICacheFactory $cacheFactory,
		TransferService $service,
		$UserId
	) {
		parent::__construct($AppName, $request);
		$this->userId = $UserId;
		$this->jobList = $jobList;
		$this->clientService = $clientService;
		$this->cacheFactory = $cacheFactory;
		$this->service = $service;
	}

	/**
	 * @NoAdminRequired
	 */
	public function transfer(string $path, string $url, string $hashAlgo, string $hash) {
		if (basename($path) === '') {
			return new DataResponse('File name is required', Http::STATUS_BAD_REQUEST);
		}

		if ($hashAlgo !== '' && !in_array($hashAlgo, ['md5', 'sha1', 'sha256', 'sha512'], true)) {
			return new DataResponse('Unsupported hash algorithm', Http::STATUS_BAD_REQUEST);
		}

		if ($hashAlgo === '' && trim($hash) !== '') {
			return new DataResponse('An algorithm is required to verify the checksum', Http::STATUS_BAD_REQUEST);
		}

		$transferId = bin2hex(random_bytes(16));

		$this->jobList->add(TransferJob::class, [
			"userId" => $this->userId,
			"path" => $path,
			"url" => $url,
			"hashAlgo" => $hashAlgo,
			"hash" => $hash,
			"transferId" => $transferId,
		]);

		return new DataResponse(true, Http::STATUS_OK);
	}

	/**
	 * Prepare an immediate transfer. Stores the parameters and returns the ID.
	 *
	 * @NoAdminRequired
	 */
	public function prepare(string $path, string $url, string $hashAlgo, string $hash) {
		if (!$this->service->isProgressAvailable()) {
			return new DataResponse('Immediate transfers require a distributed cache (Redis or Memcached).', Http::STATUS_BAD_REQUEST);
		}

		if (basename($path) === '') {
			return new DataResponse('File name is required', Http::STATUS_BAD_REQUEST);
		}

		if ($hashAlgo !== '' && !in_array($hashAlgo, ['md5', 'sha1', 'sha256', 'sha512'], true)) {
			return new DataResponse('Unsupported hash algorithm', Http::STATUS_BAD_REQUEST);
		}

		if ($hashAlgo === '' && trim($hash) !== '') {
			return new DataResponse('An algorithm is required to verify the checksum', Http::STATUS_BAD_REQUEST);
		}

		$transferId = bin2hex(random_bytes(16));

		$cache = $this->cacheFactory->createDistributed('transfer');
		$cache->set('prepared:' . $transferId, json_encode([
			"userId" => $this->userId,
			"path" => $path,
			"url" => $url,
			"hashAlgo" => $hashAlgo,
			"hash" => $hash,
		]), 300);
		$cache->set('heartbeat:' . $transferId, time(), 30);

		return new DataResponse(['transferId' => $transferId], Http::STATUS_OK);
	}

	/**
	 * Start a prepared immediate transfer. Blocks until complete.
	 *
	 * @NoAdminRequired
	 */
	public function start(string $transferId) {
		set_time_limit(0);

		if (!$this->service->isProgressAvailable()) {
			return new DataResponse('Immediate transfers require a distributed cache (Redis or Memcached).', Http::STATUS_BAD_REQUEST);
		}

		$cache = $this->cacheFactory->createDistributed('transfer');
		$data = $cache->get('prepared:' . $transferId);

		if ($data === null) {
			return new DataResponse('Transfer not found or expired', Http::STATUS_NOT_FOUND);
		}

		$params = json_decode($data, true);

		if ($params['userId'] !== $this->userId) {
			return new DataResponse('Unauthorized', Http::STATUS_FORBIDDEN);
		}

		$cache->remove('prepared:' . $transferId);

		$result = $this->service->transfer(
			$params['userId'], $params['path'], $params['url'],
			$params['hashAlgo'], $params['hash'], $transferId
		);
		// Cancellation is a deliberate client action, not a server error
		$status = $result === TransferService::RESULT_FAILED
			? Http::STATUS_INTERNAL_SERVER_ERROR
			: Http::STATUS_OK;
		return new DataResponse(['result' => $result], $status);
	}

	/**
	 * Probe a URL via HEAD request to determine the file extension.
	 *
	 * @NoAdminRequired
	 */
	public function probe(string $url) {
		try {
			$client = $this->clientService->newClient();
			$response = $client->head($url, ['timeout' => 10]);
			$contentType = $response->getHeader('Content-Type');
			// Strip charset and parameters (e.g. "image/jpeg; charset=utf-8")
			$mime = strtolower(trim(explode(';', $contentType)[0]));
			$extension = self::MIME_EXTENSIONS[$mime] ?? '';
			return new DataResponse(['extension' => $extension], Http::STATUS_OK);
		} catch (LocalServerException $e) {
			return new DataResponse(['extension' => ''], Http::STATUS_FORBIDDEN);
		} catch (\Exception $e) {
			return new DataResponse(['extension' => ''], Http::STATUS_OK);
		}
	}

	/**
	 * Get progress of active transfers for the current user.
	 *
	 * @NoAdminRequired
	 */
	public function progress(string $heartbeat = '') {
		if (!$this->service->isProgressAvailable()) {
			return new DataResponse(['error' => 'no_cache', 'message' => 'Progress tracking requires a distributed cache (Redis or Memcached).'], Http::STATUS_OK);
		}
		$cache = $this->cacheFactory->createDistributed('transfer');
		$index = json_decode($cache->get('index:' . $this->userId) ?: '[]', true);

		// Update heartbeat for the specified transfer (validate ownership)
		if ($heartbeat !== '') {
			if (in_array($heartbeat, $index, true)) {
				$cache->set('heartbeat:' . $heartbeat, time(), 30);
			}
		}

		$transfers = [];
		foreach ($index as $transferId) {
			$data = $cache->get('progress:' . $transferId);
			if ($data !== null) {
				$transfers[] = json_decode($data, true);
			}
		}
		return new DataResponse($transfers, Http::STATUS_OK);
	}

	/**
	 * Cancel an active transfer.
	 *
	 * @NoAdminRequired
	 */
	public function cancel(string $transferId) {
		if (!$this->service->isProgressAvailable()) {
			return new DataResponse(false, Http::STATUS_BAD_REQUEST);
		}
		$cache = $this->cacheFactory->createDistributed('transfer');

		// Validate the transfer belongs to the current user
		$index = json_decode($cache->get('index:' . $this->userId) ?: '[]', true);
		if (!in_array($transferId, $index, true)) {
			return new DataResponse(false, Http::STATUS_NOT_FOUND);
		}

		$cache->set('cancel:' . $transferId, '1', 300);
		return new DataResponse(true, Http::STATUS_OK);
	}
}
