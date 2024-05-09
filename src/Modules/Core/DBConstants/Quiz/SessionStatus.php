<?php

// table fs_quiz_session

namespace Foodsharing\Modules\Core\DBConstants\Quiz;

/**
 * only valid for working groups
 * TINYINT(2) | DEFAULT NULL.
 */
enum SessionStatus: int
{
    case RUNNING = 0;
    case PASSED = 1;
    case FAILED = 2;
}
