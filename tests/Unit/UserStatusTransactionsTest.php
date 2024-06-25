<?php

declare(strict_types=1);

namespace Tests\Unit;

use Codeception\Test\Unit;
use Foodsharing\Lib\Session;
use Foodsharing\Modules\Login\LoginGateway;
use Foodsharing\Modules\Login\UserStatusTransactions;
use Tests\Support\UnitTester;

class UserStatusTransactionsTest extends Unit
{
    protected $foodsaverGatewayMock;
    protected $sessionMock;
    protected $loginGatewayMock;

    protected UnitTester $tester;

    private UserStatusTransactions $userStatusTransactions;

    public function _before()
    {
        $this->sessionMock = $this->createMock(Session::class);
        $this->loginGatewayMock = $this->createMock(LoginGateway::class);
        $this->userStatusTransactions = new UserStatusTransactions($this->loginGatewayMock, $this->sessionMock);
    }

    public function testUpdateOfLastUserStateFirstTime(): void
    {
        $this->loginGatewayMock->expects($this->once())->method('getLastLogin')->willReturn('0000-00-00 00:00:00');
        $this->sessionMock->expects($this->once())->method('has')->with('LAST_USER_ACTIVITY')->willReturn(false);

        // Refresh information storages
        $this->sessionMock->expects($this->once())->method('set')->with('LAST_USER_ACTIVITY', date('Y-m-d'));
        $this->loginGatewayMock->expects($this->once())->method('updateLastActivityInDatabase')->with(1);
        $this->userStatusTransactions->updateLastUserStatus(1);
    }

    public function testUpdateOfLastUserStateNewSessionWithExistingLoginToday(): void
    {
        $this->loginGatewayMock->expects($this->once())->method('getLastLogin')->willReturn(date('Y-m-d h:i:s'));
        $this->sessionMock->expects($this->once())->method('has')->with('LAST_USER_ACTIVITY')->willReturn(false);

        $this->sessionMock->expects($this->once())->method('set')->with('LAST_USER_ACTIVITY', date('Y-m-d'));
        $this->loginGatewayMock->expects($this->never())->method('updateLastActivityInDatabase')->with(1);

        $this->userStatusTransactions->updateLastUserStatus(1);
    }

    public function testExistingLoadedDataFromDbLastActivityIsADayAgo(): void
    {
        $this->loginGatewayMock->expects($this->once())->method('getLastLogin')->willReturn(date('Y-m-d h:i:s', strtotime('-1 day')));
        $this->sessionMock->expects($this->once())->method('has')->with('LAST_USER_ACTIVITY')->willReturn(false);

        $this->sessionMock->expects($this->once())->method('set')->with('LAST_USER_ACTIVITY', date('Y-m-d'));
        $this->loginGatewayMock->expects($this->once())->method('updateLastActivityInDatabase')->with(1);

        $this->userStatusTransactions->updateLastUserStatus(1);
    }

    public function testInvalidSessionData(): void
    {
        $this->loginGatewayMock->expects($this->never())->method('getLastLogin');
        $this->sessionMock->expects($this->once())->method('has')->with('LAST_USER_ACTIVITY')->willReturn(true);
        $this->sessionMock->expects($this->once())->method('get')->with('LAST_USER_ACTIVITY')->willReturn('abc');

        $this->sessionMock->expects($this->once())->method('set')->with('LAST_USER_ACTIVITY', date('Y-m-d'));
        $this->loginGatewayMock->expects($this->once())->method('updateLastActivityInDatabase')->with(1);

        $this->userStatusTransactions->updateLastUserStatus(1);
    }

    public function testExistingLoadedDataFromSessionNoChanges(): void
    {
        $this->loginGatewayMock->expects($this->never())->method('getLastLogin');
        $this->sessionMock->expects($this->once())->method('has')->with('LAST_USER_ACTIVITY')->willReturn(true);
        $this->sessionMock->expects($this->once())->method('get')->with('LAST_USER_ACTIVITY')->willReturn(date('Y-m-d h:i:s'));

        $this->sessionMock->expects($this->never())->method('set')->with('LAST_USER_ACTIVITY', date('Y-m-d'));
        $this->loginGatewayMock->expects($this->never())->method('updateLastActivityInDatabase')->with(1);

        $this->userStatusTransactions->updateLastUserStatus(1);
    }

    public function testExistingLoadedDataFromSessionLastActivityIsADayAgo(): void
    {
        $this->loginGatewayMock->expects($this->never())->method('getLastLogin');
        $this->sessionMock->expects($this->once())->method('has')->with('LAST_USER_ACTIVITY')->willReturn(true);
        $this->sessionMock->expects($this->once())->method('get')->with('LAST_USER_ACTIVITY')->willReturn(date('Y-m-d h:i:s', strtotime('-1 day')));

        $this->sessionMock->expects($this->once())->method('set')->with('LAST_USER_ACTIVITY', date('Y-m-d'));
        $this->loginGatewayMock->expects($this->once())->method('updateLastActivityInDatabase')->with(1);

        $this->userStatusTransactions->updateLastUserStatus(1);
    }
}
