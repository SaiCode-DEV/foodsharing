<?php

namespace Foodsharing\Permissions;

use Foodsharing\Lib\Session;
use Foodsharing\Modules\Core\DBConstants\Foodsaver\Role;
use Foodsharing\Modules\Core\DBConstants\Quiz\QuizID;
use Foodsharing\Modules\Core\DBConstants\Region\RegionIDs;
use Foodsharing\Modules\Quiz\QuizGateway;
use Foodsharing\Modules\Region\RegionGateway;
use Foodsharing\Modules\Unit\CurrentUserUnitsInterface;

final class QuizPermissions
{
    public function __construct(
        private readonly Session $session,
        private readonly RegionGateway $regionGateway,
        private readonly QuizGateway $quizGateway,
        private readonly CurrentUserUnitsInterface $currentUserUnits,
    ) {
    }

    public function maySeeEditQuizPage(): bool
    {
        return $this->session->mayRole(Role::ORGA) || $this->currentUserUnits->isAdminFor(RegionIDs::QUIZ_AND_REGISTRATION_WORK_GROUP);
    }

    public function mayEditQuiz(?QuizID $quizId): bool
    {
        if ($this->session->mayRole(Role::ORGA)) {
            return true;
        }

        return match ($quizId) {
            QuizID::FOODSAVER, QuizID::STORE_MANAGER, QuizID::AMBASSADOR => $this->currentUserUnits->isAdminFor(RegionIDs::QUIZ_AND_REGISTRATION_WORK_GROUP),
            default => false,
        };
    }

    /**
     * Whether the user is allowed to read all questions, answers, explanations and comments on a quiz.
     */
    public function mayReadQuiz(?QuizID $quizId): bool
    {
        if ($this->session->mayRole(Role::ORGA)) {
            return true;
        }

        // TODO change to only if the quiz is passed
        return match ($quizId) {
            QuizID::FOODSAVER => $this->regionGateway->hasMember($this->session->id(), RegionIDs::QUIZ_AND_REGISTRATION_WORK_GROUP),
            QuizID::STORE_MANAGER, QuizID::AMBASSADOR => $this->currentUserUnits->isAdminFor(RegionIDs::QUIZ_AND_REGISTRATION_WORK_GROUP),
            default => false,
        };
    }

    /**
     * Whether the user is allowed to try answering the quiz.
     */
    public function mayTryQuiz(?QuizID $quizId): bool
    {
        if (is_null($quizId) || !$this->session->mayRole()) {
            return false;
        }

        return match ($quizId) {
            QuizID::FOODSAVER => true,

            // Allow if the user is verified and role is sufficiently high:
            QuizID::STORE_MANAGER, QuizID::AMBASSADOR => $this->session->isVerified() && $this->session->role()->value >= $quizId->value - 1,
        };
    }

    public function getReadableQuizzes(): array
    {
        if (!$this->session->mayRole()) {
            return [];
        }
        $quizzes = $this->quizGateway->getQuizzes();
        $visible = [];
        foreach ($quizzes as &$quiz) {
            $quizId = QuizID::tryFrom($quiz['id']);
            if (!$this->mayReadQuiz($quizId)) {
                continue;
            }
            $quiz['edit'] = $this->mayEditQuiz($quizId);
            $visible[] = $quiz;
        }

        return $visible;
    }
}
