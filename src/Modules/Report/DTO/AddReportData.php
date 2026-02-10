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
}
