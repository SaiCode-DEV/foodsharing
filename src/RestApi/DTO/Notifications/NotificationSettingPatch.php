<?php

declare(strict_types=1);

namespace Foodsharing\RestApi\DTO\Notifications;

use Symfony\Component\Validator\Constraints as Assert;

class NotificationSettingPatch
{
    #[Assert\NotBlank]
    #[Assert\Positive]
    public int $id;

    public ?bool $bell = null;

    public ?bool $email = null;
}
