<?php

namespace Foodsharing\Modules\Activity\DTO;

use DateTime;

class ActivityUpdateMailbox
{
    public DateTime $time;

    public string $type = 'mailbox';
    public string $desc;
    public string $title;

    public string $icon = 'fas fa-envelope';
    public string $source;

    // the email id
    public int $entity_id;

    // Individual update-type properties
    public string $sender_email;
    public int $mailboxId;

    public static function create(
        DateTime $time,
        string $desc,
        string $mailbox_name,
        int $mailboxId,
        int $emailId,
        string $subject,
        string $sender_email
    ): self {
        $u = new self();

        $u->time = $time;

        $u->desc = $desc;
        $u->title = $subject;

        $u->source = $mailbox_name;
        $u->mailboxId = $mailboxId;

        $u->entity_id = $emailId;

        $u->sender_email = $sender_email;

        return $u;
    }
}
