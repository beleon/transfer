<?php
namespace OCA\Transfer\BackgroundJob;

use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\QueuedJob;

use OCA\Transfer\Service\TransferService;

class TransferJob extends QueuedJob {
	protected $service;
	
	public function __construct(ITimeFactory $time, TransferService $service) {
		parent::__construct($time);
		$this->service = $service;
	}

	protected function run($arguments) {
		$this->service->transfer(
			$arguments["userId"],
			$arguments["path"],
			$arguments["url"],
			$arguments["hashAlgo"],
			$arguments["hash"],
			$arguments["transferId"]
		);
	}
}
