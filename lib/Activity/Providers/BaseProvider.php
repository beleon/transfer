<?php
namespace OCA\Transfer\Activity\Providers;

use OCP\Activity\IEvent;
use OCP\Activity\IManager;
use OCP\Activity\IProvider;
use OCP\IURLGenerator;
use OCP\L10N\IFactory;

abstract class BaseProvider implements IProvider {
    protected $languageFactory;
    protected $urlGenerator;

    public function __construct(
        IManager $activityManager,
        IFactory $languageFactory,
        IURLGenerator $urlGenerator
    ) {
        $this->activityManager = $activityManager;
        $this->languageFactory = $languageFactory;
        $this->urlGenerator = $urlGenerator;
    }

    protected function setIcon(IEvent $event): void {
        // TODO: Respect activityManager->getRequirePNG()
        $event->setIcon(
            $this->urlGenerator->getAbsoluteUrl(
                $this->urlGenerator->imagePath("transfer", "app-dark.svg")
            )
        );
    }
}
