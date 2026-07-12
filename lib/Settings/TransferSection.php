<?php
namespace OCA\Transfer\Settings;

use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\Settings\IIconSection;

class TransferSection implements IIconSection {
	private $urlGenerator;
	private $l;

	public function __construct(IURLGenerator $urlGenerator, IL10N $l) {
		$this->urlGenerator = $urlGenerator;
		$this->l = $l;
	}

	public function getID() {
		return 'transfer';
	}

	public function getName() {
		return $this->l->t('Transfer');
	}

	public function getPriority() {
		return 90;
	}

	public function getIcon() {
		return $this->urlGenerator->imagePath('transfer', 'app-dark.svg');
	}
}
