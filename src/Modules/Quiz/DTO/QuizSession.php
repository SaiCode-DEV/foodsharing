<?php

declare(strict_types=1);

namespace Foodsharing\Modules\Quiz\DTO;

use Carbon\Carbon;
use DateTimeInterface;
use Foodsharing\Modules\Core\DBConstants\Quiz\SessionStatus;

class QuizSession
{
    public function __construct(
        public ?int $id = null,
        public ?int $foodsaverId = null,
        public ?int $quizId = null,
        public ?SessionStatus $status = null,
        public ?int $questionsAnswered = null,
        public ?array $questions = null,
        public ?array $results = null,
        public ?DateTimeInterface $startTime = null,
        public ?DateTimeInterface $endTime = null,
        public ?float $failurePoints = null,
        public ?int $maxFailurePointsToSucceed = null,
        public ?bool $isTimed = null
    ) {
    }

    public static function createFromArray(array $data): QuizSession
    {
        $quizSession = new QuizSession(
            id: $data['id'] ?? null,
            foodsaverId: $data['foodsaver_id'] ?? null,
            quizId: $data['quiz_id'] ?? null,
            status: isset($data['status']) ? SessionStatus::from($data['status']) : null,
            questionsAnswered: $data['quiz_index'] ?? null,
            questions: json_decode($data['quiz_questions'] ?? 'null', true),
            results: json_decode($data['quiz_result'] ?? 'null', true),
            startTime: isset($data['time_start']) ? Carbon::createFromFormat('Y-m-d H:i:s', $data['time_start']) : null,
            endTime: isset($data['time_end']) ? Carbon::createFromFormat('Y-m-d H:i:s', $data['time_end']) : null,
            failurePoints: isset($data['fp']) ? (float)$data['fp'] : null,
            maxFailurePointsToSucceed: $data['maxfp'] ?? null,
            isTimed: isset($data['easymode']) ? !(bool)$data['easymode'] : null,
        );
        if (!$quizSession->questions && isset($data['quest_count'])) {
            $quizSession->questions = array_fill(0, $data['quest_count'], null);
        }

        return $quizSession;
    }
}
