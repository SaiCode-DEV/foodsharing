<?php

namespace Foodsharing\RestApi\Models\Settings;

use DateTime;
use Symfony\Component\Validator\Constraints as Assert;

class SleepStatusRequest
{
    #[Assert\NotNull]
    #[Assert\Type('integer')]
    public int $mode;

    public ?DateTime $from = null;

    public ?DateTime $to = null;

    #[Assert\Length(max: 255)]
    public ?string $message = null;
}
