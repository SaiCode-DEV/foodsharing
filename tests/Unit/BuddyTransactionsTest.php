<?php

declare(strict_types=1);

namespace Tests\Unit;

use Codeception\Test\Unit;
use Foodsharing\Lib\Session;
use Foodsharing\Modules\Bell\BellGateway;
use Foodsharing\Modules\Buddy\BuddyGateway;
use Foodsharing\Modules\Buddy\BuddyTransactions;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\Support\UnitTester;

class BuddyTransactionsTest extends Unit
{
    protected ?MockObject $session = null;
    protected UnitTester $tester;
    private ?BuddyGateway $buddyGateway = null;
    private ?BellGateway $bellGateway = null;
    private ?BuddyTransactions $transaction = null;
    private array $foodsaver;
    private array $otherFoodsaver;

    public function _before()
    {
        $this->buddyGateway = $this->tester->get(BuddyGateway::class);
        $this->bellGateway = $this->tester->get(BellGateway::class);
        $this->session = $this->createMock(Session::class);
        $this->transaction = new BuddyTransactions($this->buddyGateway, $this->bellGateway, $this->session);
        $this->foodsaver = $this->tester->createFoodsaver();
        $this->otherFoodsaver = $this->tester->createFoodsaver();
    }

    protected function _after()
    {
    }

    public function testRequestAndConfim(): void
    {
        $this->session->expects($this->any())->method('id')->willReturn($this->foodsaver['id']);
        $this->buddyGateway->buddyRequest($this->foodsaver['id'], $this->otherFoodsaver['id']);

        $this->session->expects($this->once())->method('set')->with('buddy-ids', [$this->otherFoodsaver['id']]);
        $this->transaction->acceptBuddyRequest($this->otherFoodsaver['id']);
        $this->tester->seeInDatabase('fs_buddy', [
            'foodsaver_id' => $this->foodsaver['id'],
            'buddy_id' => $this->otherFoodsaver['id'],
            'confirmed' => 1
        ]);
        $this->tester->seeInDatabase('fs_buddy', [
            'foodsaver_id' => $this->otherFoodsaver['id'],
            'buddy_id' => $this->foodsaver['id'],
            'confirmed' => 1
        ]);
    }

    public function testListMyBuddyIdsFromCache(): void
    {
        $this->session->expects($this->once())->method('get')->with('buddy-ids')->willReturn([1]);
        $ids = $this->transaction->listBuddiesIds();
        $this->assertEquals([1], $ids);
    }

    public function testListMyBuddyIdsLoadWithEmptyCache(): void
    {
        $this->tester->addBuddy($this->foodsaver['id'], $this->otherFoodsaver['id'], true);

        $this->session->expects($this->any())->method('id')->willReturn($this->foodsaver['id']);
        $this->session->expects($this->any())->method('get')->with('buddy-ids')
            ->willReturnOnConsecutiveCalls(false, [10]);
        $this->session->expects($this->once())->method('set')->with('buddy-ids', [$this->otherFoodsaver['id']]);

        $ids = $this->transaction->listBuddiesIds();
        $this->assertEquals([10], $ids);
    }
}
