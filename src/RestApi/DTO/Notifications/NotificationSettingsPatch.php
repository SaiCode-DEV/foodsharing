<?php

declare(strict_types=1);

namespace Foodsharing\RestApi\DTO\Notifications;

use Symfony\Component\Validator\Constraints as Assert;

class NotificationSettingsPatch
{
    /** @var NotificationSettingPatch[] */
    #[Assert\NotBlank]
    public array $notifications;
}
