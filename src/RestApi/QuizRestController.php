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
use Foodsharing\Modules\Quiz\QuizGateway;
use Foodsharing\Modules\Quiz\QuizSessionGateway;
use Foodsharing\Modules\Quiz\QuizTransactions;
use Foodsharing\Permissions\ProfilePermissions;
use Foodsharing\Permissions\QuizPermissions;
use FOS\RestBundle\Controller\Annotations as Rest;
use Nelmio\ApiDocBundle\Annotation\Model;
use OpenApi\Attributes as OA;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[OA\Tag(name: 'quiz')]
#[OA\Response(response: Response::HTTP_UNAUTHORIZED, description: 'Missing permissions')]
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

    #[Rest\Post('user/current/quizsessions/{quizId}/start', requirements: ['quizId' => '\d+'])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success.')]
    public function startQuizSession(
        int $quizId,
        #[MapQueryParameter] bool $isTimed = false,
        #[MapQueryParameter] bool $isTest = false,
    ): Response {
        $quiz = $this->getQuizSanityChecked($quizId);
        if ($isTest) {
            if (!$this->quizPermissions->mayReadQuiz(QuizID::from($quiz->id))) {
                throw new UnauthorizedHttpException('', 'You are not permitted to test this quiz.');
            }
        } else {
            if (!$this->quizPermissions->mayTryQuiz(QuizID::tryFrom($quiz->id))) {
                throw new UnauthorizedHttpException('', 'You are not permitted to try this quiz.');
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

    #[Rest\Get('user/current/quizsessions/{quizId}/status', requirements: ['quizId' => '\d+'])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success.', content: new Model(type: QuizStatus::class))]
    public function getQuizStatus(int $quizId, #[MapQueryParameter] bool $isTest = false): Response
    {
        $this->getQuizSanityChecked($quizId);
        $status = $this->quizTransactions->getQuizStatus(QuizID::from($quizId), $this->session->id(), $isTest);

        return $this->respondOK($status);
    }

    #[Rest\Get('user/current/quizsessions/{quizId}/question', requirements: ['quizId' => '\d+'])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success.', content: new Model(type: ActiveQuestion::class))]
    public function getNextQuestion(int $quizId, #[MapQueryParameter] bool $isTest = false): Response
    {
        $this->getQuizSanityChecked($quizId);
        $session = $this->assertSessionRunning($quizId, isTest: $isTest);

        $nextQuestion = $this->quizTransactions->getNextQuestion($session);

        return $this->respondOK($nextQuestion);
    }

    #[Rest\Post('user/current/quizsessions/{quizId}/answer', requirements: ['quizId' => '\d+'])]
    #[OA\RequestBody(content: new OA\JsonContent(type: 'array', items: new OA\Items(type: 'integer')))]
    #[ParamConverter('answerIds', class: 'array<integer>', converter: 'fos_rest.request_body')]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success.', content: new OA\JsonContent(type: 'object', properties: [
        new OA\Property(property: 'solution', type: 'array', items: new OA\Items(ref: new Model(type: Answer::class))),
        new OA\Property(property: 'timedOut', type: 'boolean', description: 'Whether the answer was given in time.'),
    ]))]
    public function answerNextQuestion(int $quizId, array $answerIds, #[MapQueryParameter] bool $isTest = false): Response
    {
        $this->getQuizSanityChecked($quizId);
        $session = $this->assertSessionRunning($quizId, isTest: $isTest);

        //Check that only answers to the question were given
        //Also allow ansering null (meaning question was not answered in time)
        $question = $session->questions[$session->questionsAnswered];
        $possibleAnswerIds = array_column($question['answers'], 'id');
        if (!in_array(null, $answerIds) && !empty(array_diff($answerIds, $possibleAnswerIds))) {
            throw new BadRequestHttpException('Invalid answerId given.');
        }

        $solutions = $this->quizTransactions->answerQuestion($session, $answerIds);

        return $this->respondOK($solutions);
    }

    #[Rest\Get('user/current/quizsessions/{quizId}/results', requirements: ['quizId' => '\d+'])]
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

    #[Rest\Post('user/current/quizsessions/{quizId}/confirm', requirements: ['quizId' => '\d+'])]
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

    #[Rest\Get('quiz/sessions/{foodsaverId}', requirements: ['foodsaverId' => '\d+'])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success.', content: new OA\JsonContent(
        type: 'array', items: new OA\Items(type: 'object', properties: [
            new OA\Property(property: 'quiz', ref: new Model(type: Quiz::class)),
            new OA\Property(property: 'sessions', type: 'array', items: new OA\Items(
                ref: new Model(type: QuizSession::class)
            )),
        ])
    ))]
    public function getQuizSessions(int $foodsaverId)
    {
        $this->assertLoggedIn();
        if (!$this->profilePermissions->maySeeQuizSessions()) {
            throw new AccessDeniedHttpException();
        }
        $sessions = $this->quizSessionGateway->getUserSessionsGroupedByQuiz($foodsaverId);

        return $this->respondOK($sessions);
    }

    #[Rest\Delete('quiz/session/{sessionId}', requirements: ['sessionId' => '\d+'])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success.')]
    public function deleteQuizSession(int $sessionId)
    {
        $this->assertLoggedIn();
        if (!$this->profilePermissions->mayDeleteQuizSessions()) {
            throw new AccessDeniedHttpException();
        }
        $this->quizSessionGateway->deleteSession($sessionId);

        return $this->respondOK();
    }

    // Editing quizzes:

    #[Rest\Get('quiz/{quizId}', requirements: ['quizId' => '\d+'])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success', content: new Model(type: Quiz::class))]
    public function getQuizDetails(int $quizId): Response
    {
        return $this->respondOK($this->getQuizSanityChecked($quizId));
    }

    #[Rest\Patch('quiz/{quizId}', requirements: ['quizId' => '\d+'])]
    #[OA\RequestBody(content: new Model(type: Quiz::class))]
    #[ParamConverter('quiz', class: Quiz::class, converter: 'fos_rest.request_body')]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success')]
    public function updateQuiz(int $quizId, Quiz $quiz, ValidatorInterface $validator): Response
    {
        $this->assertQuizAccess($quizId);
        $this->getQuizSanityChecked($quizId);
        $this->assertThereAreNoValidationErrors($validator, $quiz);
        $quiz->id = $quizId;
        $this->quizGateway->updateQuiz($quiz);

        return $this->respondOK();
    }

    #[OA\Tag(name: 'quiz', description: 'Get all questions of a quiz')]
    #[Rest\Get('quiz/{quizId}/questions', requirements: ['quizId' => '\d+'])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success', content: new Model(type: Question::class))]
    public function getQuestions(int $quizId): Response
    {
        $this->assertQuizAccess($quizId, false);
        $this->getQuizSanityChecked($quizId);
        $questions = $this->quizTransactions->listQuestions($quizId);

        return $this->handleView($this->view($questions, 200));
    }

    #[Rest\Post('quiz/{quizId}/questions', requirements: ['quizId' => '\d+'])]
    #[OA\RequestBody(content: new Model(type: Question::class))]
    #[ParamConverter('question', class: Question::class, converter: 'fos_rest.request_body')]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success',
        content: new OA\JsonContent(type: 'integer', description: 'Id of the created question'))]
    public function addQuestion(int $quizId, Question $question, ValidatorInterface $validator): Response
    {
        $this->assertQuizAccess($quizId);
        $this->getQuizSanityChecked($quizId);
        $this->assertThereAreNoValidationErrors($validator, $question);

        return $this->respondOK($this->quizGateway->addQuestion($quizId, $question));
    }

    #[Rest\Patch('quiz/{quizId}/questions/{questionId}', requirements: ['quizId' => '\d+', 'questionId' => '\d+'])]
    #[OA\RequestBody(content: new Model(type: Question::class))]
    #[ParamConverter('question', class: Question::class, converter: 'fos_rest.request_body')]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success')]
    public function updateQuestion(int $quizId, int $questionId, Question $question, ValidatorInterface $validator): Response
    {
        $this->assertQuizAccess($quizId);
        if ($quizId != $this->quizGateway->getQuizIdFromQuestionId($questionId)) {
            throw new NotFoundHttpException('Invalid id given.');
        }
        $this->getQuestionSanityChecked($questionId);
        $this->assertThereAreNoValidationErrors($validator, $question);
        $question->id = $questionId;
        $this->quizGateway->updateQuestion($question);

        return $this->respondOK();
    }

    #[Rest\Delete('quiz/{quizId}/questions/{questionId}', requirements: ['quizId' => '\d+', 'questionId' => '\d+'])]
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

    #[Rest\Post('quiz/{quizId}/questions/{questionId}/answers', requirements: ['quizId' => '\d+', 'questionId' => '\d+'])]
    #[OA\RequestBody(content: new Model(type: Answer::class))]
    #[ParamConverter('answer', class: Answer::class, converter: 'fos_rest.request_body')]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success',
        content: new OA\JsonContent(type: 'integer', description: 'Id of the created answer'))]
    public function addAnswer(int $quizId, int $questionId, Answer $answer, ValidatorInterface $validator): Response
    {
        $this->assertQuizAccess($quizId);
        if ($quizId != $this->quizGateway->getQuizIdFromQuestionId($questionId)) {
            throw new NotFoundHttpException('Invalid id given.');
        }
        $this->assertQuizAccess($this->quizGateway->getQuizIdFromQuestionId($questionId));
        $this->getQuestionSanityChecked($questionId);
        $this->assertThereAreNoValidationErrors($validator, $answer);

        return $this->respondOK($this->quizGateway->addAnswer($questionId, $answer));
    }

    #[Rest\Patch('quiz/{quizId}/questions/{questionId}/answers/{answerId}', requirements: ['quizId' => '\d+', 'questionId' => '\d+', 'answerId' => '\d+'])]
    #[OA\RequestBody(content: new Model(type: Answer::class))]
    #[ParamConverter('answer', class: Answer::class, converter: 'fos_rest.request_body')]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success')]
    public function updateAnswer(int $quizId, int $questionId, int $answerId, Answer $answer, ValidatorInterface $validator): Response
    {
        $this->assertQuizAccess($quizId);
        if ($questionId != $this->quizGateway->getQuestionIdFromAnswerId($answerId) || $quizId != $this->quizGateway->getQuizIdFromQuestionId($questionId)) {
            throw new NotFoundHttpException('Invalid id given.');
        }
        $this->getAnswerSanityChecked($answerId);
        $this->assertThereAreNoValidationErrors($validator, $answer);
        $answer->id = $answerId;
        $this->quizGateway->updateAnswer($answer);

        return $this->respondOK();
    }

    #[Rest\Delete('quiz/{quizId}/questions/{questionId}/answers/{answerId}', requirements: ['quizId' => '\d+', 'questionId' => '\d+', 'answerId' => '\d+'])]
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
        $quizId = QuizID::tryFrom($quizId);
        if ($edit && !$this->quizPermissions->mayEditQuiz($quizId)) {
            throw new UnauthorizedHttpException('', 'You are not permitted to edit this quiz');
        } elseif (!$edit && !$this->quizPermissions->mayReadQuiz($quizId)) {
            throw new UnauthorizedHttpException('', 'You are not permitted to access this quiz\'s data.');
        }
    }
}
