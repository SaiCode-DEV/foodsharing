<?php

namespace Foodsharing\Modules\Quiz;

use Carbon\Carbon;
use Foodsharing\Lib\Session;
use Foodsharing\Modules\Achievement\AchievementTransactions;
use Foodsharing\Modules\Core\DBConstants\Achievement\AchievementIDs;
use Foodsharing\Modules\Core\DBConstants\Foodsaver\Role;
use Foodsharing\Modules\Core\DBConstants\Quiz\AnswerRating;
use Foodsharing\Modules\Core\DBConstants\Quiz\QuizID;
use Foodsharing\Modules\Core\DBConstants\Quiz\SessionStatus;
use Foodsharing\Modules\Core\DBConstants\WallType;
use Foodsharing\Modules\Foodsaver\FoodsaverGateway;
use Foodsharing\Modules\Legal\LegalGateway;
use Foodsharing\Modules\Mailbox\MailboxGateway;
use Foodsharing\Modules\Quiz\DTO\ActiveQuestion;
use Foodsharing\Modules\Quiz\DTO\Question;
use Foodsharing\Modules\Quiz\DTO\Quiz;
use Foodsharing\Modules\Quiz\DTO\QuizSession;
use Foodsharing\Modules\Quiz\DTO\QuizStatus;
use Foodsharing\Modules\WallPost\WallPostGateway;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class QuizTransactions
{
    public const NETWORK_BUFFER_TIME_IN_SECONDS = 5;

    public function __construct(
        private readonly Session $session,
        private readonly QuizSessionGateway $quizSessionGateway,
        private readonly QuizGateway $quizGateway,
        private readonly FoodsaverGateway $foodsaverGateway,
        private readonly WallPostGateway $wallPostGateway,
        private readonly LegalGateway $legalGateway,
        private readonly AchievementTransactions $achievementTransactions,
        private readonly MailboxGateway $mailboxGateway,
    ) {
    }

    /**
     * Initializes and starts a new quiz session.
     * Should only be used, if no such session is currently running for the user.
     */
    public function startQuizSession(Quiz $quiz, bool $isTimed, bool $isTest): void
    {
        if ($isTest) {
            $this->quizSessionGateway->deleteUserSessions(QuizID::from($quiz->id), $this->session->id(), true);
        }
        $questionCount = $isTimed ? $quiz->questionCountTimed : $quiz->questionCountUntimed;
        $questions = $this->getFairQuestions($questionCount, $quiz->id);

        $quizSession = new QuizSession(
            foodsaverId: $this->session->id(),
            quizId: $quiz->id,
            questions: $questions,
            maxFailurePointsToSucceed: $quiz->maxFailurePointsToSucceed,
            isTimed: $isTimed,
            isTest: $isTest,
        );
        $this->quizSessionGateway->initQuizSession($quizSession);
    }

    /**
     * Selects a fair set of questions.
     * Questions are rated with different numbers of failure points on error.
     * This function makes sure to select the correct number of questions of each difficulty.
     *
     * @return array<Question>
     */
    private function getFairQuestions(int $count, int $quizId): array
    {
        $questions = $this->quizGateway->getMandatoryQuestions($quizId);
        $count -= count($questions);
        if ($count > 0) {
            $fpCounts = $this->quizGateway->getQuestionCountByFailurePoints($quizId);
            $total = array_reduce($fpCounts, fn ($a, $b) => $b['count'] + $a, 0);
            $carryOver = 0;
            foreach ($fpCounts as &$fpCount) {
                $numQuestions = $fpCount['count'] / $total * $count + $carryOver;
                $rounded = (int)round($numQuestions);
                $carryOver = $numQuestions - $rounded;
                array_push($questions, ...$this->quizGateway->getRandomQuestions($rounded, $fpCount['fp'], $quizId));
            }
        }
        foreach ($questions as &$question) {
            $question->answers = $this->quizGateway->getAnswers($question->id);
            shuffle($question->answers);
        }
        shuffle($questions);

        return $questions;
    }

    /**
     * Returns all information required to display the detailed current quiz status.
     */
    public function getQuizStatus(QuizID $quizId, int $fsId, bool $isTest = false): QuizStatus
    {
        $status = new QuizStatus();

        $sessions = $this->quizSessionGateway->collectQuizSessions($quizId, $fsId, $isTest);
        $tries = count($sessions);

        if ($tries) {
            // lastSessionStatus
            $lastSession = $sessions[0];
            $status->lastSessionStatus = $lastSession->status;
            if ($status->lastSessionStatus === SessionStatus::RUNNING) {
                --$tries; // dont count the running session
            }
        } else {
            $status->waitTimeAfterFailure = $this->getQuizWaitTime($quizId, $tries + 1);

            return $status;
        }

        $status->waitTimeAfterFailure = $this->getQuizWaitTime($quizId, $tries + 1);

        // Disqualified
        $totalWaitTime = $this->getQuizWaitTime($quizId, $tries);
        if ($totalWaitTime === -1 && $status->lastSessionStatus == SessionStatus::FAILED) {
            $status->currentWaitTime = -1;

            return $status;
        }

        // expirationTime
        $now = Carbon::now();
        $expirationTime = $this->getQuizExpirationTime($quizId);
        if ($expirationTime) {
            foreach ($sessions as $session) {
                if ($session->status === SessionStatus::PASSED) {
                    $expirationDate = (new Carbon($session->endTime))->addDays($expirationTime);
                    $daysUntilExpiration = max(0, intval(ceil($now->diffInDays($expirationDate))));
                    if ($daysUntilExpiration <= $this->getQuizExpirationWarningTime($quizId)) {
                        $status->expirationTime = $daysUntilExpiration;
                    }
                    break;
                }
            }
        }

        // currently running
        if ($status->lastSessionStatus == SessionStatus::RUNNING) {
            $status->questionCount = count($lastSession->questions);
            $status->questionsAnswered = $lastSession->questionsAnswered;
            $status->isTimed = $lastSession->isTimed;

            return $status;
        }

        // currentWaitTime
        if ($totalWaitTime && $lastSession->status === SessionStatus::FAILED) {
            $waitEndDate = (new Carbon($lastSession->endTime))->addDays($totalWaitTime);
            $status->currentWaitTime = max(0, intval(ceil($now->diffInDays($waitEndDate))));
        }

        // confirmed
        if ($status->lastSessionStatus == SessionStatus::PASSED) {
            $status->confirmed = $this->isQuizConfirmed($quizId);
        }

        return $status;
    }

    /**
     * Finalizes a quiz session.
     * This includes evaluating the quiz.
     */
    private function finalizeQuiz(QuizSession $session): void
    {
        $quiz = $this->quizGateway->getQuiz($session->quizId);
        $failurePointsTotal = 0;
        $quizLog = [];
        foreach ($session->questions as &$question) {
            $userAnswerIds = array_column($session->results, null, 'questionId')[$question['id']]['userAnswerIds'] ?? null;
            $failurePoints = 0;
            $timedOut = is_null($userAnswerIds);
            if ($timedOut) {
                $failurePoints = $question['failurePoints'];
            } else {
                foreach ($question['answers'] as &$answer) {
                    $answer['selected'] = in_array($answer['id'], $userAnswerIds);
                }
                $valuedAnswers = array_filter($question['answers'], fn ($answer) => $answer['answerRating'] !== AnswerRating::NEUTRAL->value);
                if (count($valuedAnswers)) {
                    $mistakes = array_filter($valuedAnswers, function ($answer) use ($userAnswerIds) {
                        $answerWasSelected = in_array($answer['id'], $userAnswerIds);

                        return $answerWasSelected !== (bool)$answer['answerRating'];
                    });
                    $failurePoints += round(count($mistakes) * $question['failurePoints'] / count($valuedAnswers), 2);
                }
            }

            $failurePointsTotal += $failurePoints;
            $question['timedOut'] = $timedOut;
            $question['userFailurePoints'] = $failurePoints;
            $quizLog[] = $question;
        }
        $session->questions = null;
        $session->results = $quizLog;
        $session->failurePoints = $failurePointsTotal;
        $session->status = ($failurePointsTotal <= $quiz->maxFailurePointsToSucceed) ? SessionStatus::PASSED : SessionStatus::FAILED;
        $session->endTime = Carbon::now();
        $this->quizSessionGateway->updateQuizSession($session);
        if ($session->status === SessionStatus::PASSED && !$session->isTest) {
            $this->postQuizAction(QuizID::from($quiz->id));
        }
    }

    private function postQuizAction(QuizID $quizId): void
    {
        switch ($quizId) {
            case QuizID::FOODSAVER:
            case QuizID::STORE_MANAGER:
            case QuizID::AMBASSADOR:
                $this->foodsaverGateway->riseQuizRole($this->session->id(), Role::from($quizId->value));
                break;
            case QuizID::HYGIENE:
                $this->achievementTransactions->awardAchievementFromId(AchievementIDs::HYGIENE_CERTIFICATE, $this->session->id());
                break;
        }
    }

    /**
     * returns the number of days to wait after a certain number of tries.
     * returns -1 if the user is disqualified after trying that many times.
     */
    private function getQuizWaitTime(QuizID $quizId, int $try): int
    {
        if ($try < 1) {
            // no wait before the first try
            return 0;
        }

        switch ($quizId) {
            case QuizID::FOODSAVER:
            case QuizID::STORE_MANAGER:
            case QuizID::AMBASSADOR:
                if ($try < 3) {
                    return 0;
                } if ($try == 3) {
                    return 30;
                } if ($try == 4) {
                    return 0;
                }

                return -1;
            case QuizID::HYGIENE:
                if ($try == 1) {
                    return 0;
                }

                return 1;
            default:
                return 0;
        }
    }

    /**
     * returns the time in days that a quiz stays passed before expiring, meaning that the user has to pass it again.
     */
    private function getQuizExpirationTime(QuizID $quizId): ?int
    {
        return match ($quizId) {
            QuizID::HYGIENE => 365, // one year
            default => null,
        };
    }

    /**
     * returns the time in days that a quiz will be open again before it expires.
     */
    private function getQuizExpirationWarningTime(QuizID $quizId): ?int
    {
        return match ($quizId) {
            QuizID::HYGIENE => 90, // ~3 months
            default => null,
        };
    }

    private function isQuizConfirmed(QuizID $quizId): ?bool
    {
        $confirmedRole = $this->foodsaverGateway->getRole($this->session->id());

        return match ($quizId) {
            QuizID::FOODSAVER, QuizID::STORE_MANAGER => $confirmedRole->value >= $quizId->value,
            default => null,
        };
    }

    public function updateQuizRoleForCurrentUser()
    {
        $this->session->set('quiz_role', $this->foodsaverGateway->getQuizRole($this->session->id())->value);
    }

    public function getQuizRoleOfCurrentUser(): Role
    {
        if (!$this->session->has('quiz_role')) {
            $this->updateQuizRoleForCurrentUser();
        }

        return Role::from($this->session->get('quiz_role'));
    }

    /**
     * Returns the next question in the given quiz question.
     * This might cause an exception, if the last question in the quiz session timed out.
     * @throws AccessDeniedHttpException
     */
    public function getNextQuestion(QuizSession $session, bool $timedOut = false): ActiveQuestion
    {
        $nextQuestion = new ActiveQuestion();

        if ($session->status !== SessionStatus::RUNNING) {
            throw new AccessDeniedHttpException('There must be a running quiz session.');
        }

        $questionData = $session->questions[$session->questionsAnswered];
        $nextQuestion->question = Question::createFromArray($questionData);
        $nextQuestion->question->answers = $questionData['answers'];
        $nextQuestion->timedOut = $timedOut;

        // remove solution
        foreach ($nextQuestion->question->answers as &$answer) {
            unset($answer['answerRating'], $answer['explanation']);
        }

        if (!$session->isTimed) {
            $nextQuestion->question->durationInSeconds = null;

            return $nextQuestion;
        } if (!$session->startTime) {
            $nextQuestion->questionAge = 0;
            $session->startTime = new Carbon();
            $this->quizSessionGateway->updateQuizSession($session);

            return $nextQuestion;
        }
        $nextQuestion->questionAge = time() - $session->startTime->getTimestamp();
        if ($nextQuestion->questionAge < $nextQuestion->question->durationInSeconds) {
            return $nextQuestion;
        }
        $session->startTime = null;

        $this->setNextQuestionAnswered($session);
        $this->quizSessionGateway->updateQuizSession($session);

        return $this->getNextQuestion($session, true);
    }

    /**
     * Mark the next question in a given quiz session as answered.
     */
    private function setNextQuestionAnswered(QuizSession $session): void
    {
        ++$session->questionsAnswered;
        $this->quizSessionGateway->updateQuizSession($session);

        if ($session->questionsAnswered === count($session->questions)) {
            $this->finalizeQuiz($session);
        }
    }

    /**
     * Save the given answers to the session.
     *
     * @return array<string, mixed>
     */
    public function answerQuestion(QuizSession $session, ?array $answerIds): array
    {
        $question = $session->questions[$session->questionsAnswered];
        $questionAnswered = !is_null($answerIds);

        if ($session->startTime) {
            $questionAge = time() - $session->startTime->getTimestamp();
            if ($questionAge >= (int)$question['durationInSeconds'] + $this::NETWORK_BUFFER_TIME_IN_SECONDS) {
                $questionAnswered = false;
            }
        }

        $session->startTime = null;
        $session->results ??= [];
        if ($questionAnswered) {
            $session->results[] = ['questionId' => $question['id'], 'userAnswerIds' => $answerIds];
        }
        $this->setNextQuestionAnswered($session);

        return ['solution' => $question['answers'], 'timedOut' => !$questionAnswered];
    }

    /**
     * Confirm the post-quiz question.
     * This might include legal and privacy stuff.
     * Depending on the quiz this activates the effects of passing the quiz (like rising the role).
     * Effects of future quizzes can be added here.
     */
    public function confirmQuiz(int $quizId, int $foodsaverId): bool
    {
        switch ($quizId) {
            case QuizID::STORE_MANAGER->value:
                $this->legalGateway->agreeToPrivacyNotice($this->session->id());
                $this->createUserMailbox($this->session->id());
                // no break
            case QuizID::FOODSAVER->value:
                $this->foodsaverGateway->riseRole($foodsaverId, Role::from($quizId));

                // Update session data of current session to reflect the new
                // role
                $this->session->refreshFromDatabase();
                // Invalidate all sessions of this user except the current one,
                // to apply the new role
                $this->session->invalidateAllSessionsForUser($foodsaverId, true);

                return true;
            default:
                return false;
        }
    }

    /**
     * Creates a new personal mailbox for a user who has passed the store manager quiz, if the user doesn't already have
     * one.
     */
    private function createUserMailbox(int $userId): void
    {
        $user = $this->foodsaverGateway->getFoodsaverDetails($userId);
        if ($user['mailbox_id'] === null) {
            // Make sure that first and last name are not empty
            $firstName = mb_trim($user['name']);
            $lastName = mb_trim($user['nachname']);
            if (mb_strlen($firstName) < 1 || mb_strlen($lastName) < 1) {
                throw new BadRequestHttpException('Could not create a personal mailbox if either first or last name are empty');
            }

            // Only use the first part of each name
            $firstName = explode(' ', $firstName)[0];
            $lastName = explode(' ', $lastName)[0];

            $mailboxName = mb_strtolower(mb_substr($firstName, 0, 1) . '.' . $lastName);
            $mailboxName = mb_trim($mailboxName);
            $mailboxName = str_replace(['ä', 'ö', 'ü', 'è', 'ß', 'ş', ' '], ['ae', 'oe', 'ue', 'e', 'ss', 's', '.'], $mailboxName);
            $mailboxName = preg_replace('/[^0-9a-z\.]/', '', $mailboxName) ?? '';
            $mailboxName = substr($mailboxName, 0, 25);

            // Replace trailing digit if found to avoid confusion with the numeric suffix collision avoidance later on
            $mailboxName = preg_replace('/[0-9]$/', '_', $mailboxName);

            if (strlen($mailboxName) <= 3 || $mailboxName[0] === '.') {
                throw new BadRequestHttpException('Could not create a personal mailbox with name "' . $mailboxName . '"');
            }

            $mailboxId = $this->mailboxGateway->createMailbox($mailboxName);
            $this->foodsaverGateway->setPersonalMailboxId($userId, $mailboxId);
        }
    }

    /**
     * List all questions of a quiz including details.
     *
     * @return array<Question>
     */
    public function listQuestions(int $quizId): array
    {
        $questions = $this->quizGateway->getQuestions($quizId);
        foreach ($questions as &$question) {
            $question->answers = $this->quizGateway->getAnswers($question->id);
            $question->commentCount = $this->wallPostGateway->countPosts(WallType::QUIZ_QUESTION, $question->id);
        }

        return $questions;
    }
}
