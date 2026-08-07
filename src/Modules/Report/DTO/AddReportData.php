<?php

namespace Foodsharing\Modules\Report\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class AddReportData
{
    #[Assert\NotBlank]
    public ReportReason $reason;

    #[Assert\NotBlank]
    public string $message;

    public ?int $storeId = null;
    // Optional fields for linking and admin handling
    public ?int $forumThreadId = null;
    public ?string $status = null;
    public ?string $consequence = null;

    /**
     * Whether the reporter wants a confirmation email with a summary of the report.
     */
    public bool $sendConfirmationMail = true;
}
