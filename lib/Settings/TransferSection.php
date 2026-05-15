<?php
namespace OCA\Transfer\Settings;

use OCP\IURLGenerator;
use OCP\Settings\IIconSection;

class TransferSection implements IIconSection {
	private $urlGenerator;

	public function __construct(IURLGenerator $urlGenerator) {
		$this->urlGenerator = $urlGenerator;
	}

	public function getID() {
		return 'transfer';
	}

	public function getName() {
		return 'Transfer';
	}

	public function getPriority() {
		return 90;
	}

	public function getIcon() {
		return $this->urlGenerator->imagePath('transfer', 'app-dark.svg');
	}
}
