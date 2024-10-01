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
}
