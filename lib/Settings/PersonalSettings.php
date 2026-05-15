<?php
namespace OCA\Transfer\Settings;

use OCP\AppFramework\Http\TemplateResponse;
use OCP\Settings\ISettings;
use OCP\Util;

class PersonalSettings implements ISettings {
	public function getForm() {
		Util::addScript('transfer', 'transfer-settings');
		return new TemplateResponse('transfer', 'settings/personal');
	}

	public function getSection() {
		return 'transfer';
	}

	public function getPriority() {
		return 10;
	}
}
