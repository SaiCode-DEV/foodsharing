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

    public static function parse(string $name): ?UserOptionType
    {
        $option = null;
        switch ($name) {
            case 'activity-listings':
                $option = UserOptionType::ACTIVITY_LISTINGS;
                break;
            case 'locale':
                $option = UserOptionType::LOCALE;
                break;
            default:
                $option = null;
                break;
        }

        return $option;
    }

    public function toString(): string
    {
        return match ($this) {
            UserOptionType::ACTIVITY_LISTINGS => 'activity-listings',
            UserOptionType::LOCALE => 'locale'
        };
    }
}
