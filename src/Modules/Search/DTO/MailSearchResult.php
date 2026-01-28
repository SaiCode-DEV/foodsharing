<?php

declare(strict_types=1);

namespace Foodsharing\Modules\Search\DTO;

use Carbon\Carbon;
use DateTime;
use OpenApi\Attributes as OA;

class MailSearchResult extends SearchResult
{
    #[OA\Property(description: 'The senders mail address', example: 'sender@example.com')]
    public ?string $senderMail = null;

    #[OA\Property(description: 'The senders name', example: 'Sender')]
    public ?string $senderName = null;

    #[OA\Property(description: 'The first recipients mail address', example: 'developer@foodsharing.network')]
    public ?string $recipientMail = null;

    #[OA\Property(description: 'The first recipients name.', example: 'foodsharing Developer')]
    public ?string $recipientName = null;

    #[OA\Property(description: 'The number of recipients', example: '3')]
    public int $recipientCount;

    #[OA\Property(description: 'Whether the mail has attachements', example: true)]
    public bool $hasAttachments;

    #[OA\Property(description: 'When the mail was sent', example: '2023-10-04 15:21:52')]
    public DateTime $sentAt;

    #[OA\Property(description: 'The folder the mail is saved in', example: 1)]
    public int $folder;

    public static function createFromArray(array $data): MailSearchResult
    {
        $result = new self();
        $result->id = $data['id'];
        $result->name = $data['name'];
        $result->sentAt = Carbon::parse($data['time']);
        $result->senderMail = $data['senderMail'];
        $result->senderName = $data['senderName'];
        $result->recipientMail = $data['recipientMail'];
        $result->recipientName = $data['recipientName'];
        $result->recipientCount = $data['recipientCount'];
        $result->hasAttachments = $data['attach'] !== null && $data['attach'] !== '' && $data['attach'] !== '[]';
        $result->folder = $data['folder'];
        $result->setSearchString($data);

        return $result;
    }
}
