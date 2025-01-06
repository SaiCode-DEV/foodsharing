<?php

declare(strict_types=1);

namespace Foodsharing\Modules\Search\DTO;

use OpenApi\Attributes as OA;

class MailSearchResult extends SearchResult
{
    #[OA\Property(description: 'The senders mail address', example: 'sender@example.com')]
    public ?string $sender_mail = null;

    #[OA\Property(description: 'The senders name', example: 'Sender')]
    public ?string $sender_name = null;

    #[OA\Property(description: 'The first recipients mail address', example: 'developer@foodsharing.network')]
    public ?string $recipient_mail = null;

    #[OA\Property(description: 'The first recipients name.', example: 'foodsharing Developer')]
    public ?string $recipient_name = null;

    #[OA\Property(description: 'The number of recipients', example: '3')]
    public int $recipient_count;

    #[OA\Property(description: 'Whether the mail has attachements', example: true)]
    public bool $has_attachments;

    #[OA\Property(description: 'When the mail was sent', example: '2023-10-04 15:21:52')]
    public string $time;

    #[OA\Property(description: 'The folder the mail is saved in', example: 1)]
    public int $folder;

    public static function createFromArray(array $data): MailSearchResult
    {
        $result = new self();
        $result->id = $data['id'];
        $result->name = $data['name'];
        $result->time = $data['time'];
        $result->sender_mail = $data['senderMail'];
        $result->sender_name = $data['senderName'];
        $result->recipient_mail = $data['recipientMail'];
        $result->recipient_name = $data['recipientName'];
        $result->recipient_count = $data['recipientCount'];
        $result->has_attachments = $data['attach'] !== null && $data['attach'] !== '' && $data['attach'] !== '[]';
        $result->folder = $data['folder'];
        $result->setSearchString($data);

        return $result;
    }
}
