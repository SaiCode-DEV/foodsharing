<?php

namespace Foodsharing\Modules\Report\DTO;

use DateTime;
use Foodsharing\Modules\Store\DTO\MinimalStoreIdentifier;

class ReportForListView
{
    public int $id;
    public string $message;
    public string $reason;
    public DateTime $reportedAt;
    public ?MinimalStoreIdentifier $store;
    public ProfileWithMail $reporter;
    public ProfileWithMail $reported;
}
