<?php

// table fs_quiz_session

namespace Foodsharing\Modules\Core\DBConstants\Quiz;

/**
 * only valid for working groups
 * TINYINT(2) | DEFAULT NULL.
 */
class SessionStatus
{
    final public const RUNNING = 0;
    final public const PASSED = 1;
    final public const FAILED = 2;
}
