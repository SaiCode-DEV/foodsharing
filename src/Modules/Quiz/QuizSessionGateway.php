<?php

namespace Foodsharing\Modules\Quiz;

use Foodsharing\Modules\Core\BaseGateway;
use Foodsharing\Modules\Core\DBConstants\Quiz\QuizID;
use Foodsharing\Modules\Core\DBConstants\Quiz\SessionStatus;
use Foodsharing\Modules\Quiz\DTO\QuizSession;

class QuizSessionGateway extends BaseGateway
{
    /**
     * Returns the current running session or null if no such session exists.
     */
    public function getRunningSession(int $quizId, int $fsId, bool $isTest = false): ?QuizSession
    {
        $session = $this->db->fetchByCriteria(
            'fs_quiz_session',
            '*',
            [
                'quiz_id' => $quizId,
                'foodsaver_id' => $fsId,
                'status' => SessionStatus::RUNNING->value,
                'is_test' => $isTest,
            ]
        );

        return $session ? QuizSession::createFromArray($session) : null;
    }

    public function getLatestFinishedSession(int $quizId, int $foodsaverId, bool $isTest = false): ?QuizSession
    {
        $session = $this->db->fetch('SELECT *
            FROM fs_quiz_session
            WHERE quiz_id = :quizId
            AND foodsaver_id = :foodsaverId
            AND status != :running
            AND is_test = :isTest
            ORDER BY time_end DESC', [
                ':quizId' => $quizId,
                ':foodsaverId' => $foodsaverId,
                ':running' => SessionStatus::RUNNING->value,
                ':isTest' => $isTest,
            ]);

        return $session ? QuizSession::createFromArray($session) : null;
    }

    /**
     * @return array<array<string, mixed>>
     */
    public function getUserSessionsGroupedByQuiz(int $foodsaverId): array
    {
        $sessions = $this->db->fetchAll('SELECT
				s.id, s.fp, s.maxfp, s.status, s.time_end, s.quiz_id, q.name AS quiz_name
			FROM fs_quiz_session s
			LEFT JOIN fs_quiz q ON s.quiz_id = q.id
			WHERE s.foodsaver_id = :foodsaverId AND s.is_test = 0
			ORDER BY q.id ASC, s.time_end IS NULL DESC, s.time_end DESC
		', [':foodsaverId' => $foodsaverId]);

        $groups = [];
        foreach ($sessions as &$session) {
            $group = &$groups[$session['quiz_id']];
            if (empty($group)) {
                $group = [
                    'quiz' => ['id' => $session['quiz_id'], 'name' => $session['quiz_name']],
                    'sessions' => []
                ];
            }
            $group['sessions'][] = QuizSession::createFromArray($session);
        }

        return count($groups) > 0 ? array_values($groups) : [];
    }

    /**
     * @return QuizSession[]
     */
    public function collectQuizSessions(QuizID $quizId, int $fsId, bool $isTest = false): array
    {
        $sessions = $this->db->fetchAll('SELECT
                `foodsaver_id`, `status`, `time_end`, `quiz_index`, `easymode`, `quest_count`
			FROM fs_quiz_session
			WHERE foodsaver_id = :fsId AND quiz_id = :quizId AND is_test = :isTest
            ORDER BY id DESC;
		', [':fsId' => $fsId, ':quizId' => $quizId->value, ':isTest' => $isTest]);

        return array_map(QuizSession::createFromArray(...), $sessions);
    }

    public function initQuizSession(QuizSession $quizSession): int
    {
        return $this->db->insert(
            'fs_quiz_session',
            [
                'foodsaver_id' => $quizSession->foodsaverId,
                'quiz_id' => $quizSession->quizId,
                'status' => SessionStatus::RUNNING->value,
                'quiz_index' => 0,
                'quiz_questions' => json_encode($quizSession->questions),
                'time_start' => null,
                'fp' => 0,
                'maxfp' => $quizSession->maxFailurePointsToSucceed,
                'quest_count' => count($quizSession->questions),
                'easymode' => !$quizSession->isTimed,
                'is_test' => $quizSession->isTest,
            ]
        );
    }

    public function updateQuizSession(QuizSession $session): int
    {
        return $this->db->update(
            'fs_quiz_session',
            [
                'quiz_index' => $session->questionsAnswered,
                'quiz_questions' => $session->questions ? json_encode($session->questions) : null,
                'quiz_result' => $session->results ? json_encode($session->results) : null,
                'time_start' => $session->startTime,
                'time_end' => $session->endTime,
                'status' => $session->status->value,
                'fp' => $session->failurePoints,
            ],
            ['id' => $session->id]
        );
    }

    public function deleteSession(int $sessionId): void
    {
        $this->db->delete('fs_quiz_session', ['id' => $sessionId]);
    }

    public function deleteTestSessions(QuizID $quizId, int $userId): void
    {
        $this->db->delete('fs_quiz_session', [
            'quiz_id' => $quizId->value,
            'foodsaver_id' => $userId,
            'is_test' => 1,
        ]);
    }

    /**
     * Adds five missed quiz entries for the specified user, effectively blocking the user from doing that quiz again.
     */
    public function blockUserForQuiz(int $fsId, int $quizId): void
    {
        $this->db->delete('fs_quiz_session', ['foodsaver_id' => $fsId, 'quiz_id' => $quizId]);
        for ($i = 1; $i <= 5; ++$i) {
            $this->db->insertIgnore(
                'fs_quiz_session',
                [
                    'foodsaver_id' => $fsId,
                    'quiz_id' => $quizId,
                    'status' => SessionStatus::FAILED->value,
                    'time_end' => $this->db->now()
                ]
            );
        }
    }
}
