<?php

namespace Foodsharing\Permissions;

use Foodsharing\Lib\Session;
use Foodsharing\Modules\Core\DBConstants\Foodsaver\Role;
use Foodsharing\Modules\Core\DBConstants\Quiz\QuizID;
use Foodsharing\Modules\Core\DBConstants\Quiz\SessionStatus;
use Foodsharing\Modules\Core\DBConstants\Region\RegionIDs;
use Foodsharing\Modules\Foodsaver\FoodsaverGateway;
use Foodsharing\Modules\Quiz\DTO\QuizStatus;
use Foodsharing\Modules\Quiz\QuizGateway;
use Foodsharing\Modules\Unit\CurrentUserUnitsInterface;

final readonly class QuizPermissions
{
    public function __construct(
        private Session $session,
        private FoodsaverGateway $foodsaverGateway,
        private QuizGateway $quizGateway,
        private CurrentUserUnitsInterface $currentUserUnits,
    ) {
    }

    public function responsibleGroupId(QuizID $quizId): int
    {
        return match ($quizId) {
            QuizID::FOODSAVER, QuizID::STORE_MANAGER, QuizID::AMBASSADOR => RegionIDs::QUIZ_AND_REGISTRATION_WORK_GROUP,
            QuizID::HYGIENE => RegionIDs::HYGIENE_GROUP,
            QuizID::FOODSAVER_FR => RegionIDs::QUIZ_GROUP_FR,
            QuizID::FOODSHARER, QuizID::SAVING_FOOD => RegionIDs::NEW_QUIZZES_WORK_GROUP,
        };
    }

    public function maySeeEditQuizPage(): bool
    {
        return $this->session->mayRole(Role::ORGA) ||
            $this->currentUserUnits->isAdminFor(RegionIDs::QUIZ_AND_REGISTRATION_WORK_GROUP) ||
            $this->currentUserUnits->mayBezirk(RegionIDs::HYGIENE_GROUP) ||
            $this->currentUserUnits->mayBezirk(RegionIDs::NEW_QUIZZES_WORK_GROUP);
    }

    /**
     * Whether the quiz may be edited by the user.
     * @see getQuizAdmins: make sure that every user for whom `mayEditQuiz` returns `true`, is in the list returned by that function
     */
    public function mayEditQuiz(?QuizID $quizId): bool
    {
        if ($this->session->mayRole(Role::ORGA)) {
            return true;
        }
        if (!$quizId) {
            return false;
        }

        return $this->currentUserUnits->isAdminFor($this->responsibleGroupId($quizId));
    }

    /**
     * Whether the user is allowed to read all questions, answers, explanations and comments on a quiz.
     */
    public function mayReadQuiz(?QuizID $quizId): bool
    {
        if ($this->session->mayRole(Role::ORGA)) {
            return true;
        }
        if (!$quizId) {
            return false;
        }

        $canOnlyReadAsAdmin = in_array($quizId, [QuizID::FOODSAVER, QuizID::STORE_MANAGER, QuizID::AMBASSADOR]);
        $method = $canOnlyReadAsAdmin ? 'isAdminFor' : 'mayBezirk';

        return $this->currentUserUnits->$method($this->responsibleGroupId($quizId));
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
            QuizID::HYGIENE => $this->session->mayRole(Role::FOODSHARER),
            default => false,
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

    /**
     * Returns the people who are allowed to administrate a given quiz.
     */
    public function getQuizAdmins(QuizID $quizId): array
    {
        return $this->foodsaverGateway->getAdminsOrAmbassadors($this->responsibleGroupId($quizId));
    }

    public function requiresConfirmation(QuizID $quizId): bool
    {
        return match ($quizId) {
            QuizID::FOODSAVER, QuizID::STORE_MANAGER => true,
            default => false,
        };
    }

    public function mayStartQuizNow(QuizStatus $status): bool
    {
        return !( // Not allowed when:
            $status->currentWaitTime // pause or disqualified
            || $status->lastSessionStatus === SessionStatus::RUNNING // running
            || ($status->lastSessionStatus === SessionStatus::PASSED && $status->expirationTime === -1) // passed and not expiring
        );
    }
}
