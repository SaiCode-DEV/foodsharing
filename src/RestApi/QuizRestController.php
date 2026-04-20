<?php

namespace Foodsharing\RestApi;

use Foodsharing\Lib\Session;
use Foodsharing\Modules\Core\DBConstants\Quiz\QuizID;
use Foodsharing\Modules\Core\DBConstants\Quiz\SessionStatus;
use Foodsharing\Modules\Quiz\DTO\ActiveQuestion;
use Foodsharing\Modules\Quiz\DTO\Answer;
use Foodsharing\Modules\Quiz\DTO\Question;
use Foodsharing\Modules\Quiz\DTO\Quiz;
use Foodsharing\Modules\Quiz\DTO\QuizSession;
use Foodsharing\Modules\Quiz\DTO\QuizStatus;
use Foodsharing\Modules\Quiz\DTO\SelectedAnswers;
use Foodsharing\Modules\Quiz\QuizGateway;
use Foodsharing\Modules\Quiz\QuizSessionGateway;
use Foodsharing\Modules\Quiz\QuizTransactions;
use Foodsharing\Modules\Store\DTO\CommonLabel;
use Foodsharing\Permissions\ProfilePermissions;
use Foodsharing\Permissions\QuizPermissions;
use Nelmio\ApiDocBundle\Annotation\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

#[OA\Tag(name: 'quiz')]
#[OA\Response(response: Response::HTTP_UNAUTHORIZED, description: 'Not logged in.')]
#[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Missing permissions.')]
#[OA\Response(response: Response::HTTP_BAD_REQUEST, description: 'Invalid request')]
#[OA\Response(response: Response::HTTP_NOT_FOUND, description: 'Not found')]
final class QuizRestController extends AbstractFoodsharingRestController
{
    public function __construct(
        protected Session $session,
        private readonly QuizGateway $quizGateway,
        private readonly QuizSessionGateway $quizSessionGateway,
        private readonly QuizTransactions $quizTransactions,
        private readonly ProfilePermissions $profilePermissions,
        private readonly QuizPermissions $quizPermissions,
    ) {
    }

    // Answering quizzes:

    #[OA\Post(summary: 'Starts a new quiz session')]
    #[Route('users/current/quiz-sessions/{quizId}', methods: ['POST'], requirements: ['quizId' => Requirement::POSITIVE_INT])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success.')]
    public function startQuizSession(
        int $quizId,
        #[MapQueryParameter] bool $isTimed = false,
        #[MapQueryParameter] bool $isTest = false,
    ): Response {
        $quiz = $this->getQuizSanityChecked($quizId);
        if ($isTest) {
            if (!$this->quizPermissions->mayReadQuiz(QuizID::from($quiz->id))) {
                throw new AccessDeniedHttpException('You are not permitted to test this quiz.');
            }
        } else {
            if (!$this->quizPermissions->mayTryQuiz(QuizID::tryFrom($quiz->id))) {
                throw new AccessDeniedHttpException('You are not permitted to try this quiz.');
            }
            $this->assertSessionRunning($quizId, false);
            $status = $this->quizTransactions->getQuizStatus(QuizID::from($quizId), $this->session->id());
            if (!$this->quizPermissions->mayStartQuizNow($status)) {
                throw new AccessDeniedHttpException('You are not allowed to start the quiz with your current quiz status.');
            }

            if (!$isTimed && !$quiz->questionCountUntimed) {
                throw new BadRequestHttpException('This quiz is only allowed with a time limit.');
            }
        }

        $this->quizTransactions->startQuizSession($quiz, $isTimed, $isTest);

        return $this->respondOK();
    }

    #[OA\Get(summary: 'Returns the status of the users current quiz progress')]
    #[Route('users/current/quiz-sessions/{quizId}/status', methods: ['GET'], requirements: ['quizId' => Requirement::POSITIVE_INT])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success.', content: new Model(type: QuizStatus::class))]
    public function getQuizStatus(int $quizId, #[MapQueryParameter] bool $isTest = false): Response
    {
        $this->getQuizSanityChecked($quizId);
        $status = $this->quizTransactions->getQuizStatus(QuizID::from($quizId), $this->session->id(), $isTest);

        return $this->respondOK($status);
    }

    #[OA\Get(summary: 'Returns the next question of the quiz for the currently answering user.')]
    #[Route('users/current/quiz-sessions/{quizId}/question', methods: ['GET'], requirements: ['quizId' => Requirement::POSITIVE_INT])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success.', content: new Model(type: ActiveQuestion::class))]
    public function getNextQuestion(int $quizId, #[MapQueryParameter] bool $isTest = false): Response
    {
        $this->getQuizSanityChecked($quizId);
        $session = $this->assertSessionRunning($quizId, isTest: $isTest);

        $nextQuestion = $this->quizTransactions->getNextQuestion($session);

        return $this->respondOK($nextQuestion);
    }

    #[OA\Post(summary: 'Answer the current quiz question')]
    #[Route('users/current/quiz-sessions/{quizId}/answer', methods: ['POST'], requirements: ['quizId' => Requirement::POSITIVE_INT])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success.', content: new OA\JsonContent(type: 'object', properties: [
        new OA\Property(property: 'solution', type: 'array', items: new OA\Items(ref: new Model(type: Answer::class))),
        new OA\Property(property: 'timedOut', type: 'boolean', description: 'Whether the answer was given in time.'),
    ]))]
    public function answerNextQuestion(int $quizId, #[MapRequestPayload] SelectedAnswers $answers, #[MapQueryParameter] bool $isTest = false): Response
    {
        $this->getQuizSanityChecked($quizId);
        $session = $this->assertSessionRunning($quizId, isTest: $isTest);

        //Check that only answers to the question were given
        //Also allow ansering null (meaning question was not answered in time)
        $question = $session->questions[$session->questionsAnswered];
        $possibleAnswerIds = array_column($question['answers'], 'id');
        if (!is_null($answers->ids) && !empty(array_diff($answers->ids, $possibleAnswerIds))) {
            throw new BadRequestHttpException('Invalid answerId given.');
        }

        $solutions = $this->quizTransactions->answerQuestion($session, $answers->ids);

        return $this->respondOK($solutions);
    }

    #[OA\Get(summary: 'Returns the results of the last time the current user finished the quiz')]
    #[Route('users/current/quiz-sessions/{quizId}/results', methods: ['GET'], requirements: ['quizId' => Requirement::POSITIVE_INT])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success.', content: new Model(type: QuizSession::class))]
    public function getQuizResults(int $quizId, #[MapQueryParameter] bool $isTest = false): Response
    {
        $this->getQuizSanityChecked($quizId);
        $this->assertSessionRunning($quizId, false, $isTest);

        $session = $this->quizSessionGateway->getLatestFinishedSession($quizId, $this->session->id(), $isTest);
        if (!$session) {
            throw new AccessDeniedHttpException('There must be at least one finished quiz session.');
        }

        return $this->respondOK($session);
    }

    #[OA\Post(summary: 'Confirm the finalization of a passed quiz')]
    #[Route('users/current/quiz-sessions/{quizId}/confirmation', methods: ['POST'], requirements: ['quizId' => Requirement::POSITIVE_INT])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success.')]
    public function confirmQuiz(int $quizId): Response
    {
        $this->getQuizSanityChecked($quizId);
        $status = $this->quizTransactions->getQuizStatus(QuizID::from($quizId), $this->session->id());
        if ($status->lastSessionStatus !== SessionStatus::PASSED) {
            throw new AccessDeniedHttpException('You can only finalize quizzes you passed.');
        }

        $confirmed = $this->quizTransactions->confirmQuiz($quizId, $this->session->id());
        if (!$confirmed) {
            throw new BadRequestException('This quiz can not be confirmed.');
        }

        return $this->respondOK();
    }

    private function assertSessionRunning(int $quizId, bool $isRunning = true, bool $isTest = false): ?QuizSession
    {
        $session = $this->quizSessionGateway->getRunningSession($quizId, $this->session->id(), $isTest);
        if (!$session && $isRunning) {
            throw new AccessDeniedHttpException('There must be a running quiz session.');
        } if ($session && !$isRunning) {
            throw new AccessDeniedHttpException('There must not be a running quiz session.');
        }

        return $session;
    }

    // Session management (Orga)

    #[OA\Get(summary: 'Returns a users quiz sessions')]
    #[Route('users/{userId}/quiz-sessions', methods: ['GET'], requirements: ['userId' => Requirement::POSITIVE_INT])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success.', content: new OA\JsonContent(
        type: 'array', items: new OA\Items(type: 'object', properties: [
            new OA\Property(property: 'quiz', ref: new Model(type: CommonLabel::class)),
            new OA\Property(property: 'sessions', type: 'array', items: new OA\Items(
                ref: new Model(type: QuizSession::class)
            )),
        ])
    ))]
    public function getQuizSessions(int $userId)
    {
        $this->assertLoggedIn();
        if (!$this->profilePermissions->maySeeQuizSessions()) {
            throw new AccessDeniedHttpException('Not permitted');
        }
        $sessions = $this->quizSessionGateway->getUserSessionsGroupedByQuiz($userId);

        return $this->respondOK($sessions);
    }

    #[OA\Delete(summary: 'Deletes a quiz session')]
    #[Route('quiz-sessions/{sessionId}', methods: ['DELETE'], requirements: ['sessionId' => Requirement::POSITIVE_INT])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success.')]
    public function deleteQuizSession(int $sessionId)
    {
        $this->assertLoggedIn();
        if (!$this->profilePermissions->mayDeleteQuizSessions()) {
            throw new AccessDeniedHttpException('Not permitted');
        }
        $this->quizSessionGateway->deleteSession($sessionId);

        return $this->respondOK();
    }

    // Editing quizzes:

    #[OA\Get(summary: 'Returns the details of a quiz')]
    #[Route('quizzes/{quizId}', methods: ['GET'], requirements: ['quizId' => Requirement::POSITIVE_INT])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success', content: new Model(type: Quiz::class))]
    public function getQuizDetails(int $quizId): Response
    {
        return $this->respondOK($this->getQuizSanityChecked($quizId));
    }

    #[OA\Patch(summary: 'Changes the properties of a quiz')]
    #[Route('quizzes/{quizId}', methods: ['PATCH'], requirements: ['quizId' => Requirement::POSITIVE_INT])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success')]
    public function updateQuiz(int $quizId, #[MapRequestPayload] Quiz $quiz): Response
    {
        $this->getQuizSanityChecked($quizId);
        $this->assertQuizAccess($quizId);
        $quiz->id = $quizId;
        $this->quizGateway->updateQuiz($quiz);

        return $this->respondOK();
    }

    #[OA\Get(summary: 'Returns all questions of a quiz including answers')]
    #[Route('quizzes/{quizId}/questions', methods: ['GET'], requirements: ['quizId' => Requirement::POSITIVE_INT])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success', content: new OA\JsonContent(
        type: 'array', items: new OA\Items(ref: new Model(type: Question::class))
    ))]
    public function getQuestions(int $quizId): Response
    {
        $this->getQuizSanityChecked($quizId);
        $this->assertQuizAccess($quizId, false);
        $questions = $this->quizTransactions->listQuestions($quizId);

        return $this->respondOK($questions);
    }

    #[OA\Post(summary: 'Adds a new question to a quiz')]
    #[Route('quizzes/{quizId}/questions', methods: ['POST'], requirements: ['quizId' => Requirement::POSITIVE_INT])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success', content: new OA\JsonContent(type: 'object', properties: [
        new OA\Property(property: 'id', type: 'integer', description: 'Id of the newly created question')
    ]))]
    public function addQuestion(int $quizId, #[MapRequestPayload] Question $question): Response
    {
        $this->getQuizSanityChecked($quizId);
        $this->assertQuizAccess($quizId);

        $questionId = $this->quizGateway->addQuestion($quizId, $question);

        return $this->respondOK(['id' => $questionId]);
    }

    #[OA\Patch(summary: 'Updates a quiz question')]
    #[Route('quizzes/{quizId}/questions/{questionId}', methods: ['PATCH'], requirements: ['quizId' => Requirement::POSITIVE_INT, 'questionId' => Requirement::POSITIVE_INT])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success')]
    public function updateQuestion(int $quizId, int $questionId, #[MapRequestPayload] Question $question): Response
    {
        $this->assertQuizAccess($quizId);
        if ($quizId != $this->quizGateway->getQuizIdFromQuestionId($questionId)) {
            throw new NotFoundHttpException('Invalid id given.');
        }
        $this->getQuestionSanityChecked($questionId);
        $question->id = $questionId;
        $this->quizGateway->updateQuestion($question);

        return $this->respondOK();
    }

    #[OA\Delete(summary: 'Removes a quiz question')]
    #[Route('quizzes/{quizId}/questions/{questionId}', methods: ['DELETE'], requirements: ['quizId' => Requirement::POSITIVE_INT, 'questionId' => Requirement::POSITIVE_INT])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success')]
    public function deleteQuestions(int $quizId, int $questionId): Response
    {
        $this->assertQuizAccess($quizId);
        if ($quizId != $this->quizGateway->getQuizIdFromQuestionId($questionId)) {
            throw new NotFoundHttpException('Invalid id given.');
        }
        $this->getQuestionSanityChecked($questionId);
        $this->quizGateway->deleteQuestion($questionId);

        return $this->respondOK();
    }

    #[OA\Post(summary: 'Adds a new answer to a question')]
    #[Route('quizzes/{quizId}/questions/{questionId}/answers', methods: ['POST'], requirements: ['quizId' => Requirement::POSITIVE_INT, 'questionId' => Requirement::POSITIVE_INT])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success', content: new OA\JsonContent(type: 'object', properties: [
        new OA\Property(property: 'id', type: 'integer', description: 'Id of the newly created answer')
    ]))]
    public function addAnswer(int $quizId, int $questionId, #[MapRequestPayload] Answer $answer): Response
    {
        $this->assertQuizAccess($quizId);
        if ($quizId != $this->quizGateway->getQuizIdFromQuestionId($questionId)) {
            throw new NotFoundHttpException('Invalid id given.');
        }
        $this->getQuestionSanityChecked($questionId);

        $answerId = $this->quizGateway->addAnswer($questionId, $answer);

        return $this->respondOK(['id' => $answerId]);
    }

    #[OA\Patch(summary: 'Updates an answer')]
    #[Route('quizzes/{quizId}/questions/{questionId}/answers/{answerId}', methods: ['PATCH'], requirements: ['quizId' => Requirement::POSITIVE_INT, 'questionId' => Requirement::POSITIVE_INT, 'answerId' => Requirement::POSITIVE_INT])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success')]
    public function updateAnswer(int $quizId, int $questionId, int $answerId, #[MapRequestPayload] Answer $answer): Response
    {
        $this->assertQuizAccess($quizId);
        if ($questionId != $this->quizGateway->getQuestionIdFromAnswerId($answerId) || $quizId != $this->quizGateway->getQuizIdFromQuestionId($questionId)) {
            throw new NotFoundHttpException('Invalid id given.');
        }
        $this->getAnswerSanityChecked($answerId);
        $answer->id = $answerId;
        $this->quizGateway->updateAnswer($answer);

        return $this->respondOK();
    }

    #[OA\Delete(summary: 'Removes an answer')]
    #[Route('quizzes/{quizId}/questions/{questionId}/answers/{answerId}', methods: ['DELETE'], requirements: ['quizId' => Requirement::POSITIVE_INT, 'questionId' => Requirement::POSITIVE_INT, 'answerId' => Requirement::POSITIVE_INT])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success')]
    public function deleteAnswer(int $quizId, int $questionId, int $answerId): Response
    {
        $this->assertQuizAccess($quizId);
        if ($questionId != $this->quizGateway->getQuestionIdFromAnswerId($answerId) || $quizId != $this->quizGateway->getQuizIdFromQuestionId($questionId)) {
            throw new NotFoundHttpException('Invalid id given.');
        }
        $this->getAnswerSanityChecked($answerId);
        $this->quizGateway->deleteAnswer($answerId);

        return $this->respondOK();
    }

    private function getQuizSanityChecked(int $quizId): Quiz
    {
        $this->assertLoggedIn();
        $quiz = $this->quizGateway->getQuiz($quizId);
        if (!$quiz) {
            throw new NotFoundHttpException('Invalid id given.');
        }

        return $quiz;
    }

    private function getQuestionSanityChecked(int $questionId): Question
    {
        $this->assertLoggedIn();
        $question = $this->quizGateway->getQuestion($questionId);
        if (!$question) {
            throw new NotFoundHttpException('Invalid id given.');
        }

        return $question;
    }

    private function getAnswerSanityChecked(int $answerId): Answer
    {
        $this->assertLoggedIn();
        $answer = $this->quizGateway->getAnswer($answerId);
        if (!$answer) {
            throw new NotFoundHttpException('Invalid id given.');
        }

        return $answer;
    }

    private function assertQuizAccess(int $quizId, bool $edit = true): void
    {
        $this->assertLoggedIn();
        $quizId = QuizID::tryFrom($quizId);
        if ($edit && !$this->quizPermissions->mayEditQuiz($quizId)) {
            throw new AccessDeniedHttpException('You are not permitted to edit this quiz');
        } elseif (!$edit && !$this->quizPermissions->mayReadQuiz($quizId)) {
            throw new AccessDeniedHttpException('You are not permitted to access this quiz\'s data.');
        }
    }
}
