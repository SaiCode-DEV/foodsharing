<?php

declare(strict_types=1);

namespace Foodsharing\RestApi\DTO\Notifications;

class NotificationSettingsPatch
{
    /** @var NotificationSettingPatch[] */
    public array $notifications;
}
