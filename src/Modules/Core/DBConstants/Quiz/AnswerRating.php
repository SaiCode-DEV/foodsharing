<?php

// table fs_answers

namespace Foodsharing\Modules\Core\DBConstants\Quiz;

/**
 * Table field `right`.
 */
enum AnswerRating: int
{
    case WRONG = 0;
    case CORRECT = 1;
    case NEUTRAL = 2;
}
