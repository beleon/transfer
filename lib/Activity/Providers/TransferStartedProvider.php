<?php
namespace OCA\Transfer\Activity\Providers;

use OCP\Activity\IEvent;
use OCP\Activity\Exceptions\UnknownActivityException;

class TransferStartedProvider extends BaseProvider {
    public const TYPE_TRANSFER_STARTED = "transfer_started";
    public const SUBJECT_TRANSFER_STARTED = "transfer_started";

    public function parse($language, IEvent $event, ?IEvent $previousEvent = null): IEvent {
        if ($event->getApp() !== "transfer" || $event->getType() !== self::TYPE_TRANSFER_STARTED) {
            throw new UnknownActivityException();
        }

        $l = $this->languageFactory->get("transfer", $language);
        $subject = $l->t("Transfer of {url} started");

        $subjectParameters = $event->getSubjectParameters();
        $event->setRichSubject($subject, [
            "url" => [
                "type" => "highlight",
                "id" => $subjectParameters["url"],
                "name" => $subjectParameters["url"],
            ],
        ]);

        $this->setIcon($event);
        return $event;
    }
}
