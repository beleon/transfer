<?php
namespace OCA\Transfer\Activity\Providers;

use OCP\Activity\IEvent;
use OCP\Activity\Exceptions\UnknownActivityException;

class TransferFailedProvider extends BaseProvider {
    public const TYPE_TRANSFER_FAILED = "transfer_failed";
    public const SUBJECT_TRANSFER_FAILED = "transfer_failed";
    public const SUBJECT_TRANSFER_HASH_FAILED = "transfer_hash_failed";
    public const SUBJECT_TRANSFER_BLOCKED = "transfer_blocked";

    public function parse($language, IEvent $event, ?IEvent $previousEvent = null): IEvent {
        if ($event->getApp() !== "transfer" || $event->getType() !== self::TYPE_TRANSFER_FAILED) {
            throw new UnknownActivityException();
        }

        $l = $this->languageFactory->get("transfer", $language);

        $subject = "";
        if ($event->getSubject() == self::SUBJECT_TRANSFER_FAILED) {
            $subject = $l->t("Transfer of {url} failed");
        } elseif ($event->getSubject() == self::SUBJECT_TRANSFER_HASH_FAILED) {
            $subject = $l->t("Transfer of {url} failed because its checksum did not match");
        } else {
            $subject = $l->t("Transfer of {url} was blocked because it uses a restricted address");
        }

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
