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

    #[Assert\Length(max: 5000)]
    public ?string $message = null;

    public static function create(int $mode, ?DateTime $from = null, ?DateTime $to = null, ?string $message = null): SleepStatusRequest
    {
        $s = new self();
        $s->mode = $mode;
        $s->from = $from;
        $s->to = $to;
        $s->message = $message;

        return $s;
    }
}
