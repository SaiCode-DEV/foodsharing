<?php

declare(strict_types=1);

namespace Tests\Api;

use Carbon\Carbon;
use Codeception\Example;
use Codeception\Util\HttpCode;
use Foodsharing\Modules\Core\DBConstants\Foodsaver\Role;
use Foodsharing\Modules\Core\DBConstants\Quiz\QuizStatus;
use Foodsharing\Modules\Core\DBConstants\Quiz\SessionStatus;
use Tests\Support\ApiTester;

class QuizApiCest
{
    private $foodsharer;
    private $foodsaver;
    private $foodsaverQuiz;

    public function _before(ApiTester $I): void
    {
        $this->foodsharer = $I->createFoodsharer();
        $this->foodsaver = $I->createFoodsaver();
        $this->foodsaverQuiz = $I->createQuiz(1, 3);
        $I->createQuiz(2, 3);
        $I->createQuiz(3, 3);
    }

    /**
     * @example{ "method": "Post", "url": "/api/user/current/quizsessions/1/start" }
     * @example{ "method": "Get", "url": "/api/user/current/quizsessions/1/status" }
     */
    public function cannotUseLoggedOut(ApiTester $I, Example $example): void
    {
        $I->{'send' . $example['method']}($example['url']);
        $I->seeResponseCodeIs(HttpCode::UNAUTHORIZED);
    }

    public function cannotUseInvalidQuizId(ApiTester $I): void
    {
        $I->login($this->foodsharer['email']);
        $I->sendPost('/api/user/current/quizsessions/10/start');
        $I->seeResponseCodeIs(HttpCode::NOT_FOUND);
    }

    /**
     * @example{ "isTimed": true }
     * @example{ "isTimed": false }
     */
    public function canStartQuiz(ApiTester $I, Example $example): void
    {
        $I->login($this->foodsharer['email']);
        $I->sendPost('/api/user/current/quizsessions/1/start' . ($example['isTimed'] ? '?isTimed=1' : ''));
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeInDatabase('fs_quiz_session', [
            'foodsaver_id' => $this->foodsharer['id'],
            'quiz_id' => 1,
            'easymode' => !$example['isTimed'],
            'quest_count' => $this->foodsaverQuiz[$example['isTimed'] ? 'questcount' : 'questcount_untimed'],
        ]);
    }

    /**
     * @example{ "status": 0, "allowed": true }
     * @example{ "status": 1, "allowed": false }
     * @example{ "status": 2, "allowed": false }
     * @example{ "status": 3, "allowed": true }
     * @example{ "status": 4, "allowed": false }
     * @example{ "status": 5, "allowed": true }
     * @example{ "status": 6, "allowed": false }
     */
    public function canStartQuizBasedOnStatus(ApiTester $I, Example $example): void
    {
        $this->createScenarioForQuizStatus($I, QuizStatus::from($example['status']), $this->foodsharer['id'], 1);
        $I->login($this->foodsharer['email']);

        $I->sendPost('/api/user/current/quizsessions/1/start');
        if ($example['allowed']) {
            $I->seeResponseCodeIs(HttpCode::OK);
        } else {
            $I->seeResponseCodeIs(HttpCode::FORBIDDEN);
        }
    }

    /**
     * @example{ "isTimed": true }
     * @example{ "isTimed": false }
     */
    public function canStartTimedQuizOnlyTimed(ApiTester $I, Example $example): void
    {
        $I->login($this->foodsaver['email']);
        $I->sendPost('/api/user/current/quizsessions/2/start' . ($example['isTimed'] ? '?isTimed=1' : ''));
        if ($example['isTimed']) {
            $I->seeResponseCodeIs(HttpCode::OK);
        } else {
            $I->seeResponseCodeIs(HttpCode::BAD_REQUEST);
        }
    }

    public function cannotStartQuizBeforeUnlocked(ApiTester $I): void
    {
        $I->login($this->foodsharer['email']);
        $I->sendPost('/api/user/current/quizsessions/2/start');
        $I->seeResponseCodeIs(HttpCode::UNAUTHORIZED);
        $I->dontSeeInDatabase('fs_quiz_session', [
            'quiz_id' => 1,
            'status' => SessionStatus::RUNNING->value,
        ]);
    }

    /**
     * @example{ "status": 0, "json": "{}" }
     * @example{ "status": 1, "json": "{\"questionCount\": 3, \"questionsAnswered\": 1, \"isTimed\": true}" }
     * @example{ "status": 2, "json": "{\"confirmed\": false}" }
     * @example{ "status": 3, "json": "{\"tries\": 1}" }
     * @example{ "status": 4, "json": "{\"wait\": 30}" }
     * @example{ "status": 5, "json": "{\"tries\": 3}" }
     * @example{ "status": 6, "json": "{}" }
     */
    public function canGetQuizStatus(ApiTester $I, Example $example): void
    {
        $this->createScenarioForQuizStatus($I, QuizStatus::from($example['status']), $this->foodsharer['id'], 1);
        $I->login($this->foodsharer['email']);
        $I->sendGet('/api/user/current/quizsessions/1/status');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseContainsJson(['status' => $example['status']]);
        $I->seeResponseContainsJson(json_decode((string)$example['json'], true));
    }

    public function canGetQuestionWithoutSolutions(ApiTester $I): void
    {
        $I->login($this->foodsharer['email']);
        $I->sendPost('/api/user/current/quizsessions/1/start');
        $I->sendGet('/api/user/current/quizsessions/1/question');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseContainsJson(['question' => [], 'timedOut' => false]);
        $I->seeResponseJsonMatchesJsonPath('question.answers[0].id');
        $I->dontSeeResponseJsonMatchesJsonPath('question.answers[0].answerRating');
        $I->dontSeeResponseJsonMatchesJsonPath('question.answers[0].explanation');
    }

    /**
     * @example{ "isTimed": true }
     * @example{ "isTimed": false }
     */
    public function canGetNextQuestionMultipleTimes(ApiTester $I, Example $example): void
    {
        $I->login($this->foodsharer['email']);
        $I->sendPost('/api/user/current/quizsessions/1/start' . ($example['isTimed'] ? '?isTimed=1' : ''));
        $I->sendGet('/api/user/current/quizsessions/1/question');
        $I->seeResponseCodeIs(HttpCode::OK);
        $questionId = $I->grabDataFromResponseByJsonPath('question.id')[0];

        $I->updateInDatabase('fs_quiz_session', ['time_start' => Carbon::now()->subSecond(10)], ['foodsaver_id' => $this->foodsharer['id'], 'quiz_id' => 1]);
        $I->sendGet('/api/user/current/quizsessions/1/question');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseContainsJson(['question' => ['id' => $questionId], 'timedOut' => false]);
        if ($example['isTimed']) {
            $age = $I->grabDataFromResponseByJsonPath('questionAge')[0];
            $I->assertEqualsWithDelta(10, $age, 2);
        }
    }

    /**
     * @example{ "isTimed": true }
     * @example{ "isTimed": false }
     */
    public function canGetNextQuestionAfterWaiting(ApiTester $I, Example $example): void
    {
        $I->login($this->foodsharer['email']);
        $I->sendPost('/api/user/current/quizsessions/1/start' . ($example['isTimed'] ? '?isTimed=1' : ''));
        $I->sendGet('/api/user/current/quizsessions/1/question');
        $I->seeResponseCodeIs(HttpCode::OK);
        $questionId = $I->grabDataFromResponseByJsonPath('question.id')[0];

        $I->updateInDatabase('fs_quiz_session', ['time_start' => Carbon::now()->subSecond(500)], ['foodsaver_id' => $this->foodsharer['id'], 'quiz_id' => 1]);
        $I->sendGet('/api/user/current/quizsessions/1/question');
        $I->seeResponseCodeIs(HttpCode::OK);
        if ($example['isTimed']) {
            $I->seeResponseContainsJson(['timedOut' => true]);
            $I->dontSeeResponseContainsJson(['question' => ['id' => $questionId]]);
        } else {
            $I->seeResponseContainsJson(['question' => ['id' => $questionId], 'timedOut' => false]);
        }
    }

    public function cannotGetNextQuestionWithoutRunningSession(ApiTester $I): void
    {
        $I->login($this->foodsharer['email']);
        $I->sendGet('/api/user/current/quizsessions/1/question');
        $I->seeResponseCodeIs(HttpCode::FORBIDDEN);
    }

    public function cannotGetLastQuestionAfterTimeout(ApiTester $I): void
    {
        $I->login($this->foodsharer['email']);
        $I->sendPost('/api/user/current/quizsessions/1/start?isTimed=1');
        $I->updateInDatabase('fs_quiz_session',
            ['time_start' => Carbon::now()->subSecond(500), 'quiz_index' => 2, 'quiz_result' => '[]'],
            ['foodsaver_id' => $this->foodsharer['id'], 'quiz_id' => 1],
        );
        $I->sendGet('/api/user/current/quizsessions/1/question');
        $I->seeResponseCodeIs(HttpCode::FORBIDDEN);
        $I->seeInDatabase('fs_quiz_session', [
            'foodsaver_id' => $this->foodsharer['id'],
            'quiz_id' => 1,
            'status' => SessionStatus::FAILED->value, // Failed because no question was ansered
        ]);
    }

    /**
     * @example{ "answers": "[]", "timedOut": false }
     * @example{ "answers": "useIds", "timedOut": false }
     * @example{ "answers": "[null]", "timedOut": true }
     */
    public function canAnswerQuestion(ApiTester $I, Example $example): void
    {
        $I->login($this->foodsharer['email']);
        $I->sendPost('/api/user/current/quizsessions/1/start');
        if ($example['answers'] === 'useIds') {
            $I->sendGet('/api/user/current/quizsessions/1/question');
            $answers = [
                $I->grabDataFromResponseByJsonPath('question.answers[0].id')[0],
                $I->grabDataFromResponseByJsonPath('question.answers[1].id')[0],
            ];
        } else {
            $answers = json_decode($example['answers'], true);
        }
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPost('/api/user/current/quizsessions/1/answer', $answers);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseJsonMatchesJsonPath('solution[0].id');
        $I->seeResponseJsonMatchesJsonPath('solution[0].explanation');
        $I->seeResponseJsonMatchesJsonPath('solution[0].answerRating');
        $I->seeResponseContainsJson(['timedOut' => $example['timedOut']]);
    }

    public function cannotAnswerWithoutRunningSession(ApiTester $I): void
    {
        $I->login($this->foodsharer['email']);
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPost('/api/user/current/quizsessions/1/answer', []);
        $I->seeResponseCodeIs(HttpCode::FORBIDDEN);
    }

    public function canFinishSessionByAnsweringLastQuestion(ApiTester $I): void
    {
        $I->login($this->foodsharer['email']);
        $I->sendPost('/api/user/current/quizsessions/1/start?isTimed=1');
        $I->updateInDatabase('fs_quiz_session',
            ['quiz_index' => 2, 'quiz_result' => '[]'],
            ['foodsaver_id' => $this->foodsharer['id'], 'quiz_id' => 1],
        );
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPost('/api/user/current/quizsessions/1/answer', []);
        $I->seeResponseCodeIs(HttpCode::OK);
        $status = $I->grabFromDatabase('fs_quiz_session', 'status', [
            'foodsaver_id' => $this->foodsharer['id'],
            'quiz_id' => 1,
        ]);
        $I->assertNotEquals($status, SessionStatus::RUNNING->value);
    }

    public function cannotAnswerTimedOutQuestion(ApiTester $I): void
    {
        $I->login($this->foodsharer['email']);
        $I->sendPost('/api/user/current/quizsessions/1/start?isTimed=1');
        $I->updateInDatabase('fs_quiz_session',
            ['time_start' => Carbon::now()->subSecond(500)],
            ['foodsaver_id' => $this->foodsharer['id'], 'quiz_id' => 1],
        );
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPost('/api/user/current/quizsessions/1/answer', []);
        $I->seeResponseCodeIs(HttpCode::OK);
        // See result includes solution
        $I->seeResponseJsonMatchesJsonPath('solution[0].id');
        $I->seeResponseJsonMatchesJsonPath('solution[0].explanation');
        $I->seeResponseJsonMatchesJsonPath('solution[0].answerRating');

        // ... but didn't save the given answers:
        $I->seeInDatabase('fs_quiz_session', [
            'foodsaver_id' => $this->foodsharer['id'],
            'quiz_id' => 1,
            'quiz_result' => null,
        ]);
    }

    /**
     * @example{ "status": 0, "allowed": false }
     * @example{ "status": 1, "allowed": false }
     * @example{ "status": 2, "allowed": true }
     * @example{ "status": 3, "allowed": true }
     * @example{ "status": 4, "allowed": true }
     * @example{ "status": 5, "allowed": true }
     * @example{ "status": 6, "allowed": true }
     */
    public function canGetQuizResultsBasedOnStatus(ApiTester $I, Example $example): void
    {
        $this->createScenarioForQuizStatus($I, QuizStatus::from($example['status']), $this->foodsharer['id'], 1);
        $I->login($this->foodsharer['email']);

        $I->sendGet('/api/user/current/quizsessions/1/results');
        if ($example['allowed']) {
            $I->seeResponseCodeIs(HttpCode::OK);
        } else {
            $I->seeResponseCodeIs(HttpCode::FORBIDDEN);
        }
    }

    public function canConfirmQuiz(ApiTester $I)
    {
        $I->haveInDatabase('fs_quiz_session', ['foodsaver_id' => $this->foodsharer['id'], 'quiz_id' => 1, 'status' => SessionStatus::PASSED->value]);
        $I->login($this->foodsharer['email']);
        $I->dontSeeInDatabase('fs_foodsaver', ['id' => $this->foodsharer['id'], 'rolle' => Role::FOODSAVER->value]);
        $I->sendPost('/api/user/current/quizsessions/1/confirm');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeInDatabase('fs_foodsaver', ['id' => $this->foodsharer['id'], 'rolle' => Role::FOODSAVER->value]);
    }

    public function cannotConfirmUnconfirmableQuiz(ApiTester $I)
    {
        $storeManager = $I->createStoreCoordinator();

        $I->haveInDatabase('fs_quiz_session', ['foodsaver_id' => $storeManager['id'], 'quiz_id' => 3, 'status' => SessionStatus::PASSED->value]);
        $I->login($storeManager['email']);
        $I->sendPost('/api/user/current/quizsessions/3/confirm');
        $I->seeResponseCodeIs(HttpCode::BAD_REQUEST);
    }

    public function cannotConfirmUnpassedQuiz(ApiTester $I)
    {
        $I->login($this->foodsharer['email']);
        $I->sendPost('/api/user/current/quizsessions/1/confirm');
        $I->seeResponseCodeIs(HttpCode::FORBIDDEN);
        $I->haveInDatabase('fs_quiz_session', ['foodsaver_id' => $this->foodsharer['id'], 'quiz_id' => 1, 'status' => SessionStatus::FAILED->value, 'time_end' => Carbon::now()->subDay()]);
        $I->sendPost('/api/user/current/quizsessions/1/confirm');
        $I->seeResponseCodeIs(HttpCode::FORBIDDEN);
        $I->haveInDatabase('fs_quiz_session', ['foodsaver_id' => $this->foodsharer['id'], 'quiz_id' => 1, 'status' => SessionStatus::PASSED->value]);
        $I->sendPost('/api/user/current/quizsessions/1/confirm');
        $I->seeResponseCodeIs(HttpCode::OK);
    }

    private function createScenarioForQuizStatus(ApiTester $I, QuizStatus $quizStatus, int $foodsaverId, int $quizId)
    {
        if ($quizStatus === QuizStatus::NEVER_TRIED) {
            return;
        } elseif ($quizStatus === QuizStatus::RUNNING) {
            return $I->haveInDatabase('fs_quiz_session', ['foodsaver_id' => $foodsaverId, 'quiz_id' => $quizId, 'status' => SessionStatus::RUNNING->value, 'quest_count' => 3, 'quiz_index' => 1]);
        } elseif ($quizStatus === QuizStatus::PASSED) {
            return $I->haveInDatabase('fs_quiz_session', ['foodsaver_id' => $foodsaverId, 'quiz_id' => $quizId, 'status' => SessionStatus::PASSED->value]);
        }
        $I->haveInDatabase('fs_quiz_session', ['foodsaver_id' => $foodsaverId, 'quiz_id' => $quizId, 'status' => SessionStatus::FAILED->value, 'time_end' => Carbon::now()]);
        if ($quizStatus->value >= QuizStatus::PAUSE->value) {
            $I->haveInDatabase('fs_quiz_session', ['foodsaver_id' => $foodsaverId, 'quiz_id' => $quizId, 'status' => SessionStatus::FAILED->value, 'time_end' => Carbon::now()]);
            $I->haveInDatabase('fs_quiz_session', ['foodsaver_id' => $foodsaverId, 'quiz_id' => $quizId, 'status' => SessionStatus::FAILED->value, 'time_end' => Carbon::now()]);
        } if ($quizStatus->value >= QuizStatus::PAUSE_ELAPSED->value) {
            $I->updateInDatabase('fs_quiz_session', ['time_end' => Carbon::now()->subMonth(2)], ['foodsaver_id' => $foodsaverId, 'quiz_id' => $quizId]);
        } if ($quizStatus === QuizStatus::DISQUALIFIED) {
            $I->haveInDatabase('fs_quiz_session', ['foodsaver_id' => $foodsaverId, 'quiz_id' => $quizId, 'status' => SessionStatus::FAILED->value]);
            $I->haveInDatabase('fs_quiz_session', ['foodsaver_id' => $foodsaverId, 'quiz_id' => $quizId, 'status' => SessionStatus::FAILED->value]);
        }
    }
}
