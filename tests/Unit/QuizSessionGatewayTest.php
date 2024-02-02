<?php

declare(strict_types=1);

namespace Tests\Unit;

use Codeception\Test\Unit;
use Foodsharing\Modules\Core\DBConstants\Foodsaver\Role;
use Foodsharing\Modules\Core\DBConstants\Quiz\QuizStatus;
use Foodsharing\Modules\Core\DBConstants\Quiz\SessionStatus;
use Foodsharing\Modules\Quiz\QuizSessionGateway;
use Tests\Support\UnitTester;

class QuizSessionGatewayTest extends Unit
{
    protected UnitTester $tester;
    private QuizSessionGateway $gateway;
    private array $foodsharer;
    private array $foodsaver;

    public function _before()
    {
        $this->gateway = $this->tester->get(QuizSessionGateway::class);

        $this->foodsharer = $this->tester->createFoodsharer();
        $this->foodsaver = $this->tester->createFoodsaver();
        foreach (range(1, 3) as $quizId) {
            $this->tester->createQuiz($quizId);
        }
    }

    public function testAbortQuizSession(): void
    {
        $fsId = $this->foodsaver['id'];
        $quizId = Role::AMBASSADOR->value;
        $this->tester->createQuizTry($fsId, $quizId, 0);
        $runningSessionId = $this->gateway->getRunningSession($quizId, $fsId)['id'];
        $data = ['id' => $runningSessionId, 'quiz_id' => $quizId, 'foodsaver_id' => $fsId];

        $data['status'] = SessionStatus::RUNNING;
        $this->tester->seeInDatabase('fs_quiz_session', $data);

        $this->gateway->abortSession($runningSessionId, $fsId);
        $this->tester->dontSeeInDatabase('fs_quiz_session', $data);

        $data['status'] = SessionStatus::FAILED;
        $this->tester->seeInDatabase('fs_quiz_session', $data);
    }

    public function testFoodsharerHasNeverTriedQuiz(): void
    {
        $quizStatus = $this->foodsharerQuizStatus();
        $this->tester->assertEquals(QuizStatus::NEVER_TRIED, $quizStatus['status']);
        $this->tester->assertEquals(0, $quizStatus['wait']);
    }

    public function testFoodsharerHasRunningQuizSession(): void
    {
        $this->foodsharerTriesQuiz(SessionStatus::RUNNING);
        $quizStatus = $this->foodsharerQuizStatus();
        $this->tester->assertEquals(QuizStatus::RUNNING, $quizStatus['status']);
        $this->tester->assertEquals(0, $quizStatus['wait']);
    }

    public function testFoodsharerHasPassedQuiz(): void
    {
        $this->foodsharerTriesQuiz(SessionStatus::PASSED);
        $quizStatus = $this->foodsharerQuizStatus();
        $this->tester->assertEquals(QuizStatus::PASSED, $quizStatus['status']);
        $this->tester->assertEquals(0, $quizStatus['wait']);
    }

    public function testFoodsharerFailedQuizOnce(): void
    {
        $this->foodsharerTriesQuiz(SessionStatus::FAILED);
        $quizStatus = $this->foodsharerQuizStatus();
        $this->tester->assertEquals(QuizStatus::FAILED, $quizStatus['status']);
        $this->tester->assertEquals(0, $quizStatus['wait']);
    }

    public function testFoodsharerFailedTwice(): void
    {
        $this->foodsharerTriesQuiz(SessionStatus::FAILED, 2);
        $quizStatus = $this->foodsharerQuizStatus();
        $this->tester->assertEquals(QuizStatus::FAILED, $quizStatus['status']);
        $this->tester->assertEquals(0, $quizStatus['wait']);
    }

    public function testFoodsharerIsPaused(): void
    {
        $this->foodsharerTriesQuiz(SessionStatus::FAILED, 3);
        $quizStatus = $this->foodsharerQuizStatus();
        $this->tester->assertEquals(QuizStatus::PAUSE, $quizStatus['status']);
        $this->tester->assertEquals(30, $quizStatus['wait']);
    }

    public function testFoodsharerIsPausedForOneMoreDay(): void
    {
        $this->foodsharerTriesQuiz(SessionStatus::FAILED, 3, 29);
        $quizStatus = $this->foodsharerQuizStatus();
        $this->tester->assertEquals(QuizStatus::PAUSE, $quizStatus['status']);
        $this->tester->assertEquals(1, $quizStatus['wait']);
    }

    public function testFoodsharerHasAForthTry(): void
    {
        $this->foodsharerTriesQuiz(SessionStatus::FAILED, 3, 30);
        $quizStatus = $this->foodsharerQuizStatus();
        $this->tester->assertEquals(QuizStatus::PAUSE_ELAPSED, $quizStatus['status']);
        $this->tester->assertEquals(0, $quizStatus['wait']);
    }

    public function testFoodsharerHasAFifthTry(): void
    {
        $this->foodsharerTriesQuiz(SessionStatus::FAILED, 4);
        $quizStatus = $this->foodsharerQuizStatus();
        $this->tester->assertEquals(QuizStatus::PAUSE_ELAPSED, $quizStatus['status']);
        $this->tester->assertEquals(0, $quizStatus['wait']);
    }

    public function testFoodsharerGetsDisqualifiedAfterFifthFailure(): void
    {
        $this->foodsharerTriesQuiz(SessionStatus::FAILED, 5);
        $quizStatus = $this->foodsharerQuizStatus();
        $this->tester->assertEquals(QuizStatus::DISQUALIFIED, $quizStatus['status']);
        $this->tester->assertEquals(0, $quizStatus['wait']);
    }

    private function foodsharerTriesQuiz(int $status, int $times = 1, int $daysAgo = 0): void
    {
        foreach (range(1, $times) as $i) {
            $this->tester->createQuizTry($this->foodsharer['id'], Role::FOODSAVER->value, $status, $daysAgo);
        }
    }

    private function foodsharerQuizStatus(): array
    {
        return $this->gateway->getQuizStatus(Role::FOODSAVER->value, $this->foodsharer['id']);
    }

    public function testBlockUserForQuiz(): void
    {
        $this->gateway->blockUserForQuiz($this->foodsaver['id'], Role::FOODSAVER->value);

        $this->tester->seeNumRecords(
            7,
            'fs_quiz_session',
            [
                'foodsaver_id' => $this->foodsaver['id'],
                'quiz_id' => Role::FOODSAVER->value,
                'status' => SessionStatus::FAILED
            ]
        );
    }
}
