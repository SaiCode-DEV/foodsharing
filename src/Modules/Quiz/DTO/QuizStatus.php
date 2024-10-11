<?php

declare(strict_types=1);

namespace Foodsharing\Modules\Quiz\DTO;

use Foodsharing\Modules\Core\DBConstants\Quiz\SessionStatus;
use OpenApi\Attributes as OA;

class QuizStatus
{
    #[OA\Property(description: 'The number of days until the user can try the quiz again. -1 if the user is disqualified to ever try again. Only set if the user had to wait after the last try.', example: 10)]
    public ?int $currentWaitTime = null;

    #[OA\Property(description: 'The wait time the user will get, if the quiz is failed the next time. -1 if the user gets disqualified after failing the next time.', example: -1)]
    public int $waitTimeAfterFailure;

    #[OA\Property(description: 'Status of the last quiz session. Null if no session was ever started', example: SessionStatus::FAILED)]
    public ?SessionStatus $lastSessionStatus = null;

    #[OA\Property(description: 'The number of days until the quiz expires and the user has to pass again. 0 if it is already expired, -1 if it will not expire soon.', example: -1)]
    public int $expirationTime = -1;

    // Used for running only:
    // TODO move to subclass?
    #[OA\Property(description: 'Total questions in the running quiz session. Only set if a quiz session is running.', example: 10)]
    public ?int $questionCount;

    #[OA\Property(description: 'Number of questions already answered in the running quiz session. Only set if a quiz session is running.', example: 4)]
    public ?int $questionsAnswered;

    #[OA\Property(description: 'Whether the running quiz session is timed. Only set if a quiz session is running.', example: false)]
    public ?bool $isTimed;

    // Used for passed and confirmation needed only
    #[OA\Property(description: 'Whether the quiz has been confirmed. Only set if last session was passed and the quiz needs to be confirmed.', example: null)]
    public ?bool $confirmed;
}
