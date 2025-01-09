<?php

namespace Foodsharing\Modules\Core\DBConstants\Voting;

/**
 * Type of a poll that determines how and how many options can be chosen by voters.
 *
 * Table `fs_poll`, column `type`. TINYINT(1) UNSIGNED NOT NULL.
 */
class VotingType
{
    /**
     * Users can select only one of multiple options by radio buttons.
     */
    final public const int SELECT_ONE_CHOICE = 0;
    /**
     * Users can select a variable number of options by checkboxes.
     */
    final public const int SELECT_MULTIPLE = 1;
    /**
     * Users can rate each option with a thumbs up, thumbs down, or neutral (+1, -1, 0).
     */
    final public const int THUMB_VOTING = 2;
    /**
     * Users can rate each option with a value from -3 to +3.
     */
    final public const int SCORE_VOTING = 3;

    public static function isValidType(int $scope): bool
    {
        return in_array($scope, range(self::SELECT_ONE_CHOICE, self::SCORE_VOTING));
    }

    /**
     * Returns the number of possible values that each option in a poll with the specific scope can have.
     */
    public static function getNumberOfValues(int $scope): int
    {
        return match ($scope) {
            self::SELECT_ONE_CHOICE, self::SELECT_MULTIPLE => 1,
            self::THUMB_VOTING => 3,
            self::SCORE_VOTING => 7,
            default => -1,
        };
    }
}
