<?php
namespace OCA\Transfer\Activity\Settings;

use OCA\Transfer\Activity\Providers\TransferSucceededProvider;

use OCP\Activity\ActivitySettings;
use OCP\IL10N;

class TransferSucceededSetting extends ActivitySettings {
    private $l;

	public function __construct(IL10N $l) {
		$this->l = $l;
	}

	public function getIdentifier(): string {
		return TransferSucceededProvider::TYPE_TRANSFER_SUCCEEDED;
	}

	public function getName(): string {
		return $this->l->t("An upload by link was successful");
	}

	public function getGroupIdentifier(): string {
		return "files";
	}

	public function getGroupName(): string {
		return $this->l->t("Files");
	}

	public function getPriority(): int {
		return 30;
	}

	public function canChangeNotification(): bool {
		return true;
	}

	public function isDefaultEnabledNotification(): bool {
		return true;
	}

	public function canChangeMail(): bool {
		return true;
	}

	public function isDefaultEnabledMail(): bool {
		return false;
	}
}
