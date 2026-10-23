<?php
namespace OCA\Transfer\Activity\Providers;

use OCP\Activity\IEvent;

class TransferFailedProvider extends BaseProvider {
    public const TYPE_TRANSFER_FAILED = "transfer_failed";
    public const SUBJECT_TRANSFER_FAILED = "transfer_failed";
    public const SUBJECT_TRANSFER_HASH_FAILED = "transfer_hash_failed";
    public const SUBJECT_TRANSFER_BLOCKED = "transfer_blocked";
    public const SUBJECT_TRANSFER_CANCELLED = "transfer_cancelled";

    public function parse($language, IEvent $event, ?IEvent $previousEvent = null) {
        if ($event->getApp() !== "transfer" || $event->getType() !== self::TYPE_TRANSFER_FAILED) {
            throw $this->unknownEvent();
        }

        $l = $this->languageFactory->get("transfer", $language);

        $subjectParameters = $event->getSubjectParameters();
        $parameters = [
            "url" => [
                "type" => "highlight",
                "id" => $subjectParameters["url"],
                "name" => $subjectParameters["url"],
            ],
        ];

        $subject = "";
        if ($event->getSubject() == self::SUBJECT_TRANSFER_FAILED) {
            $reason = $this->reasonText($l, $subjectParameters);
            if ($reason === "") {
                $subject = $l->t("Transfer of {url} failed");
            } else {
                $subject = $l->t("Transfer of {url} failed ({reason})");
                $parameters["reason"] = [
                    "type" => "highlight",
                    "id" => "reason",
                    "name" => $reason,
                ];
            }
        } elseif ($event->getSubject() == self::SUBJECT_TRANSFER_HASH_FAILED) {
            $subject = $l->t("Transfer of {url} failed because its checksum did not match");
        } elseif ($event->getSubject() == self::SUBJECT_TRANSFER_CANCELLED) {
            $subject = $l->t("Transfer of {url} was cancelled");
        } else {
            $subject = $l->t("Transfer of {url} was blocked because it uses a restricted address");
        }

        $event->setRichSubject($subject, $parameters);

        $this->setIcon($event);
        return $event;
    }

    /**
     * Human-readable failure reason from the subject parameters, or an
     * empty string for events published before reasons were recorded.
     */
    protected function reasonText($l, array $subjectParameters): string {
        switch ($subjectParameters["reason"] ?? "") {
            case "http_error":
                return $l->t("the remote server responded with status %s", [(string)($subjectParameters["httpStatus"] ?? "")]);
            case "connect_error":
                return $l->t("the remote server could not be reached");
            case "folder_missing":
                return $l->t("the target folder does not exist");
            case "write_error":
                return $l->t("the file could not be written");
            case "error":
                return $l->t("unexpected error");
            default:
                return "";
        }
    }
}
