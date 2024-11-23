<?php

declare(strict_types=1);

namespace Foodsharing\Modules\Development\FeatureToggles\Enums;

/**
 * For each feature toggle, please add an enum case in SCREAMING_SNAKE_CASE with same name as value in lowerCamelCase.
 * After adding or removing some feature toggle definition, please run the command foodsharing:update:featuretoggles.
 * For more description and usage about feature toggles, please visit the devdocs.
 */
enum FeatureToggleDefinitions: string
{
    case ACHIEVEMENT_SYSTEM = 'achievementSystem';
    case MAIL_SEARCH = 'mailSearch';
    case HYGIENE_QUIZ = 'hygieneQuiz';
    case PETITION_BANNER = 'petitionBanner';

    /**
     * Returns all feature toggle identifiers.
     *
     * @return array<int, string>
     */
    public static function all(): array
    {
        $definitions = array_column(self::cases(), 'value');

        return array_values($definitions);
    }
}
