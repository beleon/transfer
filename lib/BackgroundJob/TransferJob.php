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
		// Nextcloud only lifts the PHP time limit for CLI cron. Under the
		// default ajax mode a large download would die on max_execution_time.
		@set_time_limit(0);
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
