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

    /**
     * Exception to throw when an event does not belong to this provider.
     *
     * Nextcloud 30 introduced UnknownActivityException for this and logs a
     * deprecation warning whenever a provider throws a plain
     * InvalidArgumentException instead. The class does not exist on
     * Nextcloud 29, so fall back there. UnknownActivityException extends
     * InvalidArgumentException, so callers can handle both the same way.
     */
    protected function unknownEvent(): \InvalidArgumentException {
        $class = "OCP\\Activity\\Exceptions\\UnknownActivityException";
        if (class_exists($class)) {
            return new $class();
        }
        return new \InvalidArgumentException();
    }

    protected function setIcon(IEvent $event) {
        // TODO: Respect activityManager->getRequirePNG()
        $event->setIcon(
            $this->urlGenerator->getAbsoluteUrl(
                $this->urlGenerator->imagePath("transfer", "app-dark.svg")
            )
        );
    }
}
