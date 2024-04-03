<?php

declare(strict_types=1);

namespace Tests\Unit;

use Codeception\Test\Unit;
use Foodsharing\Lib\Session;
use Foodsharing\Modules\Activity\ActivityGateway;
use Foodsharing\Modules\Activity\ActivityTransactions;
use Foodsharing\Modules\Buddy\BuddyTransactions;
use Foodsharing\Modules\Core\DBConstants\Foodsaver\UserOptionType;
use Foodsharing\Modules\Mailbox\MailboxGateway;
use Foodsharing\Modules\Settings\SettingsTransactions;
use Foodsharing\Utility\ImageHelper;
use Symfony\Contracts\Translation\TranslatorInterface;
use Tests\Support\UnitTester;

class ActivityTransactionsTest extends Unit
{
    protected $settingsTransaction;
    protected $sessionTransaction;
    protected ?ActivityTransactions $transaction;
    protected UnitTester $tester;

    public function _before()
    {
        $this->settingsTransaction = $this->createMock(SettingsTransactions::class);
        $this->sessionTransaction = $this->createMock(Session::class);
        $this->transaction = new ActivityTransactions(
            $this->tester->get(ActivityGateway::class),
            $this->tester->get(MailboxGateway::class),
            $this->tester->get(ImageHelper::class),
            $this->tester->get(TranslatorInterface::class),
            $this->sessionTransaction,
            $this->settingsTransaction,
            $this->tester->get(BuddyTransactions::class)
        );
    }

    public function testGetUpdateDataWithInvalidJsonAsActivityListSetting(): void
    {
        $pageId = 0;

        $this->sessionTransaction->expects($this->any())->method('id')->willReturn(1);
        $this->settingsTransaction->expects($this->any())->method('getOption')->with(UserOptionType::ACTIVITY_LISTINGS)->willReturn('{');
        $updates = $this->transaction->getUpdateData($pageId);
    }

    public function testGetUpdateDataWithStdClassAsActivityListSetting(): void
    {
        $pageId = 0;
        $this->sessionTransaction->expects($this->any())->method('id')->willReturn(1);
        $data = [
            'bezirk-1' => ['index' => 'bezirk', 'id' => 1],
            'bezirk-2' => ['index' => 'bezirk', 'id' => 2],
        ];

        $this->settingsTransaction->expects($this->any())->method('getOption')->with(UserOptionType::ACTIVITY_LISTINGS)->willReturn(json_encode($data));
        $updates = $this->transaction->getUpdateData($pageId);
    }
}
