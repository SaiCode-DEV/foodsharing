<?php

namespace Tests\Unit;

use Codeception\Example;
use Codeception\Test\Unit;
use Foodsharing\Lib\Session;
use Foodsharing\Modules\Achievement\AchievementTransactions;
use Foodsharing\Modules\Core\DBConstants\Content\ContentId;
use Foodsharing\Modules\Core\DBConstants\Foodsaver\Role;
use Foodsharing\Modules\Core\DBConstants\Quiz\QuizID;
use Foodsharing\Modules\Core\DBConstants\Quiz\SessionStatus;
use Foodsharing\Modules\Foodsaver\FoodsaverGateway;
use Foodsharing\Modules\Legal\LegalGateway;
use Foodsharing\Modules\Mailbox\MailboxGateway;
use Foodsharing\Modules\Quiz\QuizGateway;
use Foodsharing\Modules\Quiz\QuizSessionGateway;
use Foodsharing\Modules\Quiz\QuizTransactions;
use Foodsharing\Modules\WallPost\WallPostGateway;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Tests\Support\UnitTester;

class QuizTransactionsTest extends Unit
{
    protected UnitTester $tester;
    protected ?MockObject $session = null;
    private readonly QuizTransactions $quizTransactions;

    public function _before()
    {
        $this->session = $this->createMock(Session::class);
        $this->quizTransactions = new QuizTransactions(
            $this->session,
            $this->tester->get(QuizSessionGateway::class),
            $this->tester->get(QuizGateway::class),
            $this->tester->get(FoodsaverGateway::class),
            $this->tester->get(WallPostGateway::class),
            $this->tester->get(LegalGateway::class),
            $this->tester->get(AchievementTransactions::class),
            $this->tester->get(MailboxGateway::class),
        );
    }

    /**
     * Examples contain first name, last name, and expected mailbox. Setting the third value to false means that mailbox
     * creation is supposed to throw an exception.
     *
     * @example[["Max", "Muster", "m.muster"]]
     * @example[["Erika", "Ämuster", "e.aemuster"]]
     * @example[["Max", "Ömuster", "m.oemuster"]]
     * @example[["Erika", "Ümuster", "e.uemuster"]]
     * @example[["Ärika", "Muster", "ae.muster"]]
     * @example[["Örika", "Muster", "oe.muster"]]
     * @example[["Ürika", "Muster", "ue.muster"]]
     * @example[["", "Muster", false]]
     * @example[["Max", "", false]]
     */
    public function testConfirmStoreManagerQuiz(array $example)
    {
        // Create a foodsaver who has just finished the store manager quiz
        $user = $this->tester->createFoodsaver(null, ['name' => $example[0], 'nachname' => $example[1]]);
        $this->session->method('id')->willReturn($user['id']);
        $this->tester->createQuizTry($user['id'], QuizID::STORE_MANAGER->value, SessionStatus::PASSED->value, 1);

        if (!$example[2]) {
            $this->expectException(BadRequestHttpException::class);
        }

        // Confirm the quiz
        $this->quizTransactions->confirmQuiz(QuizID::STORE_MANAGER->value, $user['id']);

        if ($example[2]) {
            // The user's role should be raised to store manager and the privacy notice should be accepted
            $privacyPolicyDate = $this->tester->grabFromDatabase('fs_content', 'last_mod', ['id' => ContentId::PRIVACY_NOTICE_CONTENT]);
            $this->tester->seeInDatabase('fs_foodsaver', [
                'rolle' => Role::STORE_MANAGER->value,
                'privacy_notice_accepted_date' => $privacyPolicyDate
            ], ['id' => $user['id']]);

            // The mailbox should exist and have the expected name
            $mailboxId = $this->tester->grabFromDatabase('fs_foodsaver', 'mailbox_id', ['id' => $user['id']]);
            $this->assertNotNull($mailboxId);
            $this->tester->seeInDatabase('fs_mailbox', ['id' => $mailboxId]);
            $this->assertEquals($example[2], $this->tester->grabFromDatabase('fs_mailbox', 'name', ['id' => $mailboxId]));
        } else {
            // In case of an error, the user's role should be not raised to store manager and the mailbox should not exist
            $this->tester->seeInDatabase('fs_foodsaver', [
                'rolle' => Role::FOODSAVER->value,
                'mailbox_id' => null,
            ], ['id' => $user['id']]);
        }
    }
}
