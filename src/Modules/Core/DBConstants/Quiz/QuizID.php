<?php

// table fs_answers

namespace Foodsharing\Modules\Core\DBConstants\Quiz;

/**
 * Table field `right`.
 */
enum QuizID: int
{
    case FOODSAVER = 1;
    case STORE_MANAGER = 2;
    case AMBASSADOR = 3;
    case HYGIENE = 4;
    case FOODSAVER_FR = 5;
    case FOODSHARER = 6;
    case SAVING_FOOD = 7;
    case REPORTS = 8;

    /**
     * @return QuizID[] all quizzes that are used for upgrading the user role
     */
    public static function quizzesForRoles(): array
    {
        return [QuizID::FOODSAVER, QuizID::FOODSAVER_FR, QuizID::STORE_MANAGER, QuizID::AMBASSADOR];
    }
}
