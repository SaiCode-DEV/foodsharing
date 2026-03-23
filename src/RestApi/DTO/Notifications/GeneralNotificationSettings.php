<?php

declare(strict_types=1);

namespace Foodsharing\RestApi\DTO\Notifications;

use OpenApi\Attributes as OA;

class GeneralNotificationSettings
{
    #[OA\Property(description: 'Whether emails are sent when the user recieves chat messages. Can be sent as null to keep the current value.')]
    public ?bool $emailOnChatMessage = null;

    #[OA\Property(description: 'Whether the user is reminded on open pickup slots in their managed stores. Can be sent as null to keep the current value. Only set for store managers.')]
    public ?bool $emailOnStoreManagerPickupReminder = null;

    #[OA\Property(description: 'Whether bells are sent when the user is mentioned via @<id>. Can be sent as null to keep the current value.')]
    public ?bool $bellOnMention = null;
}
