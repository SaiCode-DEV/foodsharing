<?php

namespace Foodsharing\Modules\Core\DBConstants\Foodsaver;

/**
 * Types of user-specific settings. Corresponds to column 'option_type' in 'fs_foodsaver_has_options'.
 * See {@see SettingsGateway::getUserOption()} and {@see SettingsGateway::setUserOption()}.
 */
enum UserOptionType: int
{
    case LOCALE = 1;
    case ACTIVITY_LISTINGS = 2;
    case DISABLE_PICKUP_REMINDER = 3;

    /**
     * If this option is set for a user, they don't get bells for being mentioned in one of their forums.
     */
    case DISABLE_MENTION_NOTIFICATION = 4;

    public static function parse(string $name): ?UserOptionType
    {
        return match ($name) {
            'activity-listings' => UserOptionType::ACTIVITY_LISTINGS,
            'locale' => UserOptionType::LOCALE,
            'disable_pickup_reminder' => UserOptionType::DISABLE_PICKUP_REMINDER,
            'disable_mention_notification' => UserOptionType::DISABLE_MENTION_NOTIFICATION,
            default => null,
        };
    }

    public function toString(): string
    {
        return match ($this) {
            UserOptionType::ACTIVITY_LISTINGS => 'activity-listings',
            UserOptionType::LOCALE => 'locale',
            UserOptionType::DISABLE_PICKUP_REMINDER => 'disable_pickup_reminder',
            UserOptionType::DISABLE_MENTION_NOTIFICATION => 'disable_mention_notification',
        };
    }
}
