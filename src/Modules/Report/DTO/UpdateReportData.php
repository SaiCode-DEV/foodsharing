<?php

namespace Foodsharing\Modules\Report\DTO;

use OpenApi\Attributes as OA;

class UpdateReportData
{
    #[OA\Property(description: 'Forum thread linked to the report. Always sent by the client, null removes the link.', type: 'integer', nullable: true)]
    public ?int $forumThreadId = null;

    public ?string $status = null;

    #[OA\Property(description: 'Consequence of the report. Always sent by the client, null removes the consequence.', type: 'string', nullable: true)]
    public ?string $consequence = null;

    #[OA\Property(description: 'Date of the reminder for the report. Always sent by the client, null removes the reminder.', type: 'string', nullable: true)]
    public ?string $reminderAt = null;
}
