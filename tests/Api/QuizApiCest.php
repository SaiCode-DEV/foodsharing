<?php

declare(strict_types=1);

namespace Tests\Api;

use Carbon\Carbon;
use Codeception\Example;
use Codeception\Util\HttpCode;
use Foodsharing\Modules\Core\DBConstants\Foodsaver\Role;
use Foodsharing\Modules\Core\DBConstants\Quiz\QuizID;
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
        $I->createQuiz(4, 3);
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
     * @example{ "scenario": 0, "mayStart": true, "mayResults": false, "json": "{\"currentWaitTime\":null,\"waitTimeAfterFailure\":0,\"lastSessionStatus\":null,\"expirationTime\":-1}" }
     * @example{ "scenario": 1, "mayStart": false, "mayResults": false, "json": "{\"currentWaitTime\":null,\"waitTimeAfterFailure\":0,\"lastSessionStatus\":0,\"questionCount\": 3, \"questionsAnswered\": 1, \"isTimed\": true}" }
     * @example{ "scenario": 2, "mayStart": false, "mayResults": true, "json": "{\"currentWaitTime\":null,\"waitTimeAfterFailure\":0,\"lastSessionStatus\":1,\"confirmed\": false}" }
     * @example{ "scenario": 3, "mayStart": true, "mayResults": true, "json": "{\"currentWaitTime\":null,\"waitTimeAfterFailure\":0,\"lastSessionStatus\":2}" }
     * @example{ "scenario": 4, "mayStart": false, "mayResults": true, "json": "{\"currentWaitTime\": 30,\"lastSessionStatus\":2,\"waitTimeAfterFailure\":0}" }
     * @example{ "scenario": 5, "mayStart": true, "mayResults": true, "json": "{\"currentWaitTime\":0,\"waitTimeAfterFailure\":0,\"lastSessionStatus\":2}" }
     * @example{ "scenario": 6, "mayStart": false, "mayResults": true, "json": "{\"currentWaitTime\":-1,\"lastSessionStatus\":2}" }
     */
    public function testSingularQuizScenarios(ApiTester $I, Example $example): void
    {
        $this->createScenarioForFoodsaverQuizStatus($I, $example['scenario'], $this->foodsharer['id']);
        $I->login($this->foodsharer['email']);

        // Status
        $I->sendGet('/api/user/current/quizsessions/1/status');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseContainsJson(json_decode((string)$example['json'], true));

        // Results
        $I->sendGet('/api/user/current/quizsessions/1/results');
        if ($example['mayResults']) {
            $I->seeResponseCodeIs(HttpCode::OK);
        } else {
            $I->seeResponseCodeIs(HttpCode::FORBIDDEN);
        }

        // Start
        $I->sendPost('/api/user/current/quizsessions/1/start');
        if ($example['mayStart']) {
            $I->seeResponseCodeIs(HttpCode::OK);
        } else {
            $I->seeResponseCodeIs(HttpCode::FORBIDDEN);
        }
    }

    /**
     * @example{ "scenario": 0, "mayStart": true, "json": "{\"currentWaitTime\":null,\"waitTimeAfterFailure\":0,\"lastSessionStatus\":null,\"expirationTime\":-1}" }
     * @example{ "scenario": 1, "mayStart": false, "json": "{\"currentWaitTime\":null,\"waitTimeAfterFailure\":0,\"lastSessionStatus\":0,\"expirationTime\":-1,\"questionCount\":3,\"questionsAnswered\":1,\"isTimed\":true}" }
     * @example{ "scenario": 2, "mayStart": false, "json": "{\"currentWaitTime\":null,\"waitTimeAfterFailure\":1,\"lastSessionStatus\":1,\"expirationTime\":-1}" }
     * @example{ "scenario": 3, "mayStart": true, "json": "{\"currentWaitTime\":null,\"waitTimeAfterFailure\":1,\"lastSessionStatus\":1,\"expirationTime\":30}" }
     * @example{ "scenario": 4, "mayStart": true, "json": "{\"currentWaitTime\":null,\"waitTimeAfterFailure\":1,\"lastSessionStatus\":1,\"expirationTime\":0}" }
     * @example{ "scenario": 5, "mayStart": false, "json": "{\"currentWaitTime\":1,\"waitTimeAfterFailure\":1,\"lastSessionStatus\":2,\"expirationTime\":0}" }
     * @example{ "scenario": 6, "mayStart": true, "json": "{\"currentWaitTime\":0,\"waitTimeAfterFailure\":1,\"lastSessionStatus\":2,\"expirationTime\":0}" }
     * @example{ "scenario": 7, "mayStart": false, "json": "{\"currentWaitTime\":null,\"waitTimeAfterFailure\":1,\"lastSessionStatus\":1,\"expirationTime\":-1}" }
     * @example{ "scenario": 8, "mayStart": true, "json": "{\"currentWaitTime\":null,\"waitTimeAfterFailure\":1,\"lastSessionStatus\":1,\"expirationTime\":30}" }
     * @example{ "scenario": 9, "mayStart": true, "json": "{\"currentWaitTime\":null,\"waitTimeAfterFailure\":1,\"lastSessionStatus\":1,\"expirationTime\":0}" }
     */
    public function testRepeatableQuizScenarios(ApiTester $I, Example $example): void
    {
        $this->createScenarioForHygieneQuizStatus($I, $example['scenario'], $this->foodsaver['id']);
        $I->login($this->foodsaver['email']);

        // Status
        $I->sendGet('/api/user/current/quizsessions/4/status');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseContainsJson(json_decode((string)$example['json'], true));

        // Start
        $I->sendPost('/api/user/current/quizsessions/4/start?isTimed=1');
        if ($example['mayStart']) {
            $I->seeResponseCodeIs(HttpCode::OK);
        } else {
            $I->seeResponseCodeIs(HttpCode::FORBIDDEN);
        }
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

        $I->updateInDatabase('fs_quiz_session', ['time_start' => Carbon::now()->subSeconds(10)], ['foodsaver_id' => $this->foodsharer['id'], 'quiz_id' => 1]);
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

        $I->updateInDatabase('fs_quiz_session', ['time_start' => Carbon::now()->subSeconds(500)], ['foodsaver_id' => $this->foodsharer['id'], 'quiz_id' => 1]);
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
            ['time_start' => Carbon::now()->subSeconds(500), 'quiz_index' => 2, 'quiz_result' => '[]'],
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
            $answers = json_decode((string)$example['answers'], true);
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
            ['time_start' => Carbon::now()->subSeconds(500)],
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
        $I->haveInDatabase('fs_quiz_session', ['foodsaver_id' => $this->foodsharer['id'], 'quiz_id' => 1, 'status' => SessionStatus::PASSED->value, 'time_end' => Carbon::now()]);
        $I->sendPost('/api/user/current/quizsessions/1/confirm');
        $I->seeResponseCodeIs(HttpCode::OK);
    }

    /**
     * Scenarios:
     * 0: never tried
     * 1: running
     * 2: passed
     * 3: failed once
     * 4: failed 3 times and therefore pausing
     * 5: pause elapsed
     * 6: disqualified
     */
    private function createScenarioForFoodsaverQuizStatus(ApiTester $I, int $scenarioId, int $foodsaverId)
    {
        $quizId = QuizID::FOODSAVER->value;
        if ($scenarioId === 0) {
            return;
        } elseif ($scenarioId === 1) {
            return $I->haveInDatabase('fs_quiz_session', ['foodsaver_id' => $foodsaverId, 'quiz_id' => $quizId, 'status' => SessionStatus::RUNNING->value, 'quest_count' => 3, 'quiz_index' => 1]);
        } elseif ($scenarioId === 2) {
            return $I->haveInDatabase('fs_quiz_session', ['foodsaver_id' => $foodsaverId, 'quiz_id' => $quizId, 'status' => SessionStatus::PASSED->value]);
        }
        $I->haveInDatabase('fs_quiz_session', ['foodsaver_id' => $foodsaverId, 'quiz_id' => $quizId, 'status' => SessionStatus::FAILED->value, 'time_end' => Carbon::now()]);
        if ($scenarioId >= 4) {
            $I->haveInDatabase('fs_quiz_session', ['foodsaver_id' => $foodsaverId, 'quiz_id' => $quizId, 'status' => SessionStatus::FAILED->value, 'time_end' => Carbon::now()]);
            $I->haveInDatabase('fs_quiz_session', ['foodsaver_id' => $foodsaverId, 'quiz_id' => $quizId, 'status' => SessionStatus::FAILED->value, 'time_end' => Carbon::now()]);
        } if ($scenarioId >= 5) {
            $I->updateInDatabase('fs_quiz_session', ['time_end' => Carbon::now()->subMonths(2)], ['foodsaver_id' => $foodsaverId, 'quiz_id' => $quizId]);
        } if ($scenarioId === 6) {
            $I->haveInDatabase('fs_quiz_session', ['foodsaver_id' => $foodsaverId, 'quiz_id' => $quizId, 'status' => SessionStatus::FAILED->value]);
            $I->haveInDatabase('fs_quiz_session', ['foodsaver_id' => $foodsaverId, 'quiz_id' => $quizId, 'status' => SessionStatus::FAILED->value]);
        }
    }

    /**
     * Scenarios:
     * 0: never tried
     * 1: running
     * 2: passed
     * 3: passed and waited 335 days (expires soon)
     * 4: passed and waited long (expired)
     * 5: failed after expiration (pause)
     * 6: pause elapsed
     * 7: passed second time
     * 8: passed second time and waited 700 days (almost expired again)
     * 9: passed second time and waited 800 days (expired again)
     */
    private function createScenarioForHygieneQuizStatus(ApiTester $I, int $scenarioId, int $foodsaverId)
    {
        $quizRepeatTimeInDays = 365;
        $quizId = QuizID::HYGIENE->value;
        if ($scenarioId === 1) {
            return $I->haveInDatabase('fs_quiz_session', ['foodsaver_id' => $foodsaverId, 'quiz_id' => $quizId, 'status' => SessionStatus::RUNNING->value, 'quest_count' => 3, 'quiz_index' => 1]);
        } if ($scenarioId >= 2) {
            $I->haveInDatabase('fs_quiz_session', ['foodsaver_id' => $foodsaverId, 'quiz_id' => $quizId, 'status' => SessionStatus::PASSED->value, 'time_end' => Carbon::now()]);
        } if ($scenarioId === 3) {
            return $I->updateInDatabase('fs_quiz_session', ['time_end' => Carbon::now()->subDays($quizRepeatTimeInDays - 30)], ['foodsaver_id' => $foodsaverId, 'quiz_id' => $quizId]);
        } if ($scenarioId >= 4) {
            $I->updateInDatabase('fs_quiz_session', ['time_end' => Carbon::now()->subDays($quizRepeatTimeInDays * 2)], ['foodsaver_id' => $foodsaverId, 'quiz_id' => $quizId]);
        } if ($scenarioId >= 5) {
            $I->haveInDatabase('fs_quiz_session', ['foodsaver_id' => $foodsaverId, 'quiz_id' => $quizId, 'status' => SessionStatus::FAILED->value, 'time_end' => Carbon::now(), 'maxfp' => 2]);
        } if ($scenarioId >= 6) {
            $I->updateInDatabase('fs_quiz_session', ['time_end' => Carbon::now()->subDays(2)], ['maxfp' => 2]);
        } if ($scenarioId >= 7) {
            $I->haveInDatabase('fs_quiz_session', ['foodsaver_id' => $foodsaverId, 'quiz_id' => $quizId, 'status' => SessionStatus::PASSED->value, 'time_end' => Carbon::now(), 'maxfp' => 1]); // maxfp used to distinguish
        } if ($scenarioId >= 8) {
            $waitTime = $scenarioId === 8 ? $quizRepeatTimeInDays - 30 : $quizRepeatTimeInDays + 10;
            $I->updateInDatabase('fs_quiz_session', ['time_end' => Carbon::now()->subDays($waitTime)], ['maxfp' => 1]);
            $I->updateInDatabase('fs_quiz_session', ['time_end' => Carbon::now()->subDays($waitTime + 2)], ['maxfp' => 2]);
        }
    }
}
