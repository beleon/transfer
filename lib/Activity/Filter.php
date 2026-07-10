<?php
namespace OCA\Transfer\Activity;

use OCP\Activity\IFilter;
use OCP\IL10N;
use OCP\IURLGenerator;

class Filter implements IFilter {
	private $l;

	private $url;

	public function __construct(IL10N $l, IURLGenerator $url) {
		$this->l = $l;
		$this->url = $url;
	}

	public function getIdentifier(): string {
		return "transfer";
	}

	public function getName(): string {
		return $this->l->t("Upload by link");
	}

	public function getPriority(): int {
		return 30;
	}

	public function getIcon(): string {
		return $this->url->imagePath("transfer", "app-dark.svg");
	}

	public function filterTypes(array $types): array {
		return $types;
	}

	public function allowedApps(): array {
		return ["transfer"];
	}
}
