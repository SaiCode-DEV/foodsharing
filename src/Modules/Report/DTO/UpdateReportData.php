<?php

namespace Foodsharing\Modules\Report\DTO;

class UpdateReportData
{
    public ?int $forumThreadId = null;
    public ?string $status = null;
    public ?string $consequence = null;
    public ?string $reminderAt = null;
}
