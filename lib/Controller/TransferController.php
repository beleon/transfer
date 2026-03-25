<?php
namespace OCA\Transfer\Controller;

use OCP\BackgroundJob\IJobList;
use OCP\Http\Client\IClientService;
use OCP\Http\Client\LocalServerException;
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
		TransferService $service,
		$UserId
	) {
		parent::__construct($AppName, $request);
		$this->userId = $UserId;
		$this->jobList = $jobList;
		$this->clientService = $clientService;
		$this->service = $service;
	}

	/**
	 * @NoAdminRequired
	 */
	public function transfer(string $path, string $url, string $hashAlgo, string $hash) {
		$this->jobList->add(TransferJob::class, [
			"userId" => $this->userId,
			"path" => $path,
			"url" => $url,
			"hashAlgo" => $hashAlgo,
			"hash" => $hash,
		]);

		return new DataResponse(true, Http::STATUS_OK);
	}

	/**
	 * Probe a URL via HEAD request to determine the file extension.
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
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
}
