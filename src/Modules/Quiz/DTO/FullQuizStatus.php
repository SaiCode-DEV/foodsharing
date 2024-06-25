<?php

declare(strict_types=1);

namespace Foodsharing\Modules\Quiz\DTO;

use Foodsharing\Modules\Core\DBConstants\Quiz\QuizStatus;
use OpenApi\Attributes as OA;

class FullQuizStatus
{
    #[OA\Property(description: 'Quiz status identifier for the given quiz.', example: QuizStatus::RUNNING)]
    public QuizStatus $status;

    #[OA\Property(description: 'Total questions in the running quiz session. Only set if a quiz session is running.', example: 10)]
    public ?int $questionCount;

    #[OA\Property(description: 'Number of questions already answered in the running quiz session. Only set if a quiz session is running.', example: 4)]
    public ?int $questionsAnswered;

    #[OA\Property(description: 'Whether the running quiz session is timed. Only set if a quiz session is running.', example: false)]
    public ?bool $isTimed;

    #[OA\Property(description: 'The number of times the user already tried the quiz. Only set if a at least one try was failed and the quiz can be started.', example: null)]
    public ?int $tries;

    #[OA\Property(description: 'The number of days until the user can try the quiz again. Only set if the user has to wait.', example: null)]
    public ?int $wait;

    #[OA\Property(description: 'Whether the quiz has been confirmed. Only set if last session was passed and the quiz needs to be confirmed.', example: null)]
    public ?bool $confirmed;
}
