<?php

namespace Foodsharing\Modules\Quiz;

use Carbon\Carbon;
use Foodsharing\Lib\Session;
use Foodsharing\Modules\Core\DBConstants\Foodsaver\Role;
use Foodsharing\Modules\Core\DBConstants\Quiz\AnswerRating;
use Foodsharing\Modules\Core\DBConstants\Quiz\QuizID;
use Foodsharing\Modules\Core\DBConstants\Quiz\QuizStatus;
use Foodsharing\Modules\Core\DBConstants\Quiz\SessionStatus;
use Foodsharing\Modules\Foodsaver\FoodsaverGateway;
use Foodsharing\Modules\Legal\LegalGateway;
use Foodsharing\Modules\Quiz\DTO\ActiveQuestion;
use Foodsharing\Modules\Quiz\DTO\FullQuizStatus;
use Foodsharing\Modules\Quiz\DTO\Question;
use Foodsharing\Modules\Quiz\DTO\Quiz;
use Foodsharing\Modules\Quiz\DTO\QuizSession;
use Foodsharing\Modules\WallPost\WallPostGateway;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class QuizTransactions
{
    public const NETWORK_BUFFER_TIME_IN_SECONDS = 5;
    public const TRIES_BEFORE_PAUSE = 3;
    public const PAUSE_DURATION_IN_DAYS = 30;
    public const TOTAL_MAX_TRIES = 5;

    public function __construct(
        private readonly Session $session,
        private readonly QuizSessionGateway $quizSessionGateway,
        private readonly QuizGateway $quizGateway,
        private readonly FoodsaverGateway $foodsaverGateway,
        private readonly WallPostGateway $wallPostGateway,
        private readonly LegalGateway $legalGateway,
    ) {
    }

    /**
     * Initializes and starts a new quiz session.
     * Should only be used, if no such session is currently running for the user.
     */
    public function startQuizSession(Quiz $quiz, bool $isTimed): void
    {
        $questionCount = $isTimed ? $quiz->questionCountTimed : $quiz->questionCountUntimed;
        $questions = $this->getFairQuestions($questionCount, $quiz->id);

        $quizSession = new QuizSession(
            foodsaverId: $this->session->id(),
            quizId: $quiz->id,
            questions: $questions,
            maxFailurePointsToSucceed: $quiz->maxFailurePointsToSucceed,
            isTimed: $isTimed
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
        $questions = [];
        $fpCounts = $this->quizGateway->getQuestionCountByFailurePoints($quizId);
        $total = array_reduce($fpCounts, fn ($a, $b) => $b['count'] + $a, 0);
        $carryOver = 0;
        foreach ($fpCounts as &$fpCount) {
            $numQuestions = $fpCount['count'] / $total * $count + $carryOver;
            $rounded = (int)round($numQuestions);
            $carryOver = $numQuestions - $rounded;
            array_push($questions, ...$this->quizGateway->getRandomQuestions($rounded, $fpCount['fp'], $quizId));
        }
        foreach ($questions as &$question) {
            $question->answers = $this->quizGateway->getAnswers($question->id);
        }
        shuffle($questions);

        return array_values($questions);
    }

    /**
     * Returns all information required to display the detailed current quiz status.
     */
    public function getQuizStatus(int $quizId, int $fsId): FullQuizStatus
    {
        [$lastSession, $tries] = $this->quizSessionGateway->collectQuizStatus($quizId, $fsId);
        $status = new FullQuizStatus();
        if (!$tries) {
            $status->status = QuizStatus::NEVER_TRIED;

            return $status;
        } if ($lastSession->status === SessionStatus::RUNNING) {
            $status->status = QuizStatus::RUNNING;
            $status->questionCount = count($lastSession->questions);
            $status->questionsAnswered = $lastSession->questionsAnswered;
            $status->isTimed = $lastSession->isTimed;

            return $status;
        } if ($lastSession->status === SessionStatus::PASSED) {
            $status->status = QuizStatus::PASSED;
            $confirmedRole = $this->foodsaverGateway->getRole($this->session->id());
            switch ($quizId) {
                case QuizID::FOODSAVER->value:
                case QuizID::STORE_MANAGER->value:
                    $status->confirmed = $confirmedRole->value >= $quizId;
                    // no break
                default:
                    return $status;
            }
        } if ($tries < self::TRIES_BEFORE_PAUSE) {
            $status->status = QuizStatus::FAILED;
            $status->tries = $tries;

            return $status;
        }
        $now = Carbon::now();
        $pauseEnd = $lastSession->endTime->copy()->addDays(self::PAUSE_DURATION_IN_DAYS);
        if ($tries === self::TRIES_BEFORE_PAUSE && $now->isBefore($pauseEnd)) {
            $status->status = QuizStatus::PAUSE;
            $status->wait = intval(round($now->floatDiffInDays($pauseEnd)));

            return $status;
        } if ($tries < self::TOTAL_MAX_TRIES) {
            $status->status = QuizStatus::PAUSE_ELAPSED;
            $status->tries = $tries;

            return $status;
        }

        $status->status = QuizStatus::DISQUALIFIED;

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
        if ($session->status === SessionStatus::PASSED) {
            switch ($quiz->id) {
                case QuizID::FOODSAVER->value:
                    $this->foodsaverGateway->riseQuizRole($this->session->id(), Role::FOODSAVER);
                    break;
                case QuizID::STORE_MANAGER->value:
                    $this->foodsaverGateway->riseQuizRole($this->session->id(), Role::STORE_MANAGER);
                    break;
                case QuizID::AMBASSADOR->value:
                    $this->foodsaverGateway->riseQuizRole($this->session->id(), Role::AMBASSADOR);
                    break;
            }
        }
        $this->session->updateQuizRole();
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
    public function answerQuestion(QuizSession $session, array $answerIds): array
    {
        $question = $session->questions[$session->questionsAnswered];
        $questionAnswered = true;

        if ($session->startTime) {
            $questionAge = time() - $session->startTime->getTimestamp();
            if ($questionAge >= (int)$question['durationInSeconds'] + $this::NETWORK_BUFFER_TIME_IN_SECONDS) {
                $questionAnswered = false;
            }
        }
        if (in_array(null, $answerIds)) {
            $questionAnswered = false;
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
                $currentPrivacyNoticeVersion = $this->legalGateway->getPnVersion();
                $this->legalGateway->agreeToPn($this->session->id(), $currentPrivacyNoticeVersion);
                // no break
            case QuizID::FOODSAVER->value:
                $this->foodsaverGateway->riseRole($foodsaverId, Role::from($quizId));
                $this->session->refreshFromDatabase();

                return true;
            default:
                return false;
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
            $question->commentCount = $this->wallPostGateway->countPosts('question', $question->id);
        }

        return $questions;
    }
}
