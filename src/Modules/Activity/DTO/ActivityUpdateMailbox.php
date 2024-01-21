<?php

declare(strict_types=1);

namespace Foodsharing\Modules\Activity\DTO;

use DateTime;
use JMS\Serializer\Annotation\SerializedName;
use JMS\Serializer\Annotation\Type;
use OpenApi\Attributes as OA;

class ActivityUpdateMailbox
{
    #[OA\Property(title: 'Type of the Update', type: 'string', default: 'mailbox')]
    #[Type('string')]
    public string $type = 'mailbox';

    #[OA\Property(title: 'The Time of the Update', type: 'string')]
    #[Type('DateTime')]
    public DateTime $time;

    #[OA\Property(title: 'The Name of the Update', type: 'string')]
    #[Type('string')]
    public string $title;

    #[OA\Property(title: 'a more detailed description', type: 'string')]
    #[Type('string')]
    public string $desc;

    #[OA\Property(title: 'The Source of the Update', type: 'string')]
    #[Type('string')]
    public string $source;

    #[OA\Property(title: 'Icon path for the update', type: 'string', default: 'fas fa-envelope')]
    #[Type('string')]
    public string $icon = 'fas fa-envelope';

    #[OA\Property(property: 'entity_id', title: 'the e-mail id', type: 'integer')]
    #[Type('int')]
    #[SerializedName('entity_id')]
    public int $entityId;

    #[OA\Property(property: 'sender_email', title: 'Sender of the email', type: 'string')]
    #[Type('string')]
    #[SerializedName('sender_email')]
    public string $senderEmail;

    #[OA\Property(title: 'id of the mailbox', type: 'integer')]
    #[Type('int')]
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
        $update = new self();

        $update->time = $time;

        $update->desc = $desc;
        $update->title = $subject;

        $update->source = $mailbox_name;
        $update->mailboxId = $mailboxId;

        $update->entityId = $emailId;

        $update->senderEmail = $sender_email;

        return $update;
    }
}
