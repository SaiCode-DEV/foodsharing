<?php

namespace Foodsharing\Modules\Report\DTO;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

class AddReportData
{
    #[OA\Property(description: 'Report reason selected by the reporter')]
    #[Assert\NotBlank]
    public ReportReason $reason;

    #[OA\Property(description: 'User generated message by the reporter')]
    #[Assert\NotBlank]
    public string $message;

    #[OA\Property(description: 'ID of the store to which the report is related, or null if it is not related to a store')]
    #[Assert\Positive]
    public ?int $storeId = null;

    // Optional fields for linking and admin handling
    #[OA\Property(description: 'ID of a forum thread which is linked to the report')]
    #[Assert\Positive]
    public ?int $forumThreadId = null;

    #[OA\Property(description: 'Current status of the processing')]
    public ?string $status = null;

    #[OA\Property(description: 'Consequence of the processed report for the reportee')]
    public ?string $consequence = null;

    #[OA\Property(description: 'Whether the reporter wants a confirmation email with a summary of the report.')]
    public bool $sendConfirmationMail = true;
}
