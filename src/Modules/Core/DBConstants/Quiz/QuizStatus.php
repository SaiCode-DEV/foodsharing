<?php

namespace Foodsharing\Modules\Core\DBConstants\Quiz;

enum QuizStatus: int
{
    /**
     * User never tried to solve the quiz.
     */
    case NEVER_TRIED = 0;

    /**
     * There is a quiz solving in progress.
     */
    case RUNNING = 1;

    /**
     * Quiz had been passed.
     */
    case PASSED = 2;

    /**
     * User failed to pass the quiz. The number of failures is less than three.
     */
    case FAILED = 3;

    /**
     * User failed to solve the quiz three times. There is a pause of 30 days before the next try.
     */
    case PAUSE = 4;

    /**
     * A 30-days pause after failing three times elapsed. The quiz is open for another two tries to solve.
     */
    case PAUSE_ELAPSED = 5;

    /**
     * All tries to solve the quiz were unsuccessful.
     */
    case DISQUALIFIED = 6;
}
