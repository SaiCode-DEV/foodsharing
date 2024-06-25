<?php

declare(strict_types=1);

namespace Tests\Unit;

use Codeception\Test\Unit;
use Foodsharing\Modules\Bell\BellGateway;
use Foodsharing\Modules\Bell\BellTransactions;
use Foodsharing\Modules\Bell\DTO\Bell;
use Tests\Support\UnitTester;

class BellTransactionsTest extends Unit
{
    protected UnitTester $tester;
    private BellTransactions $bellTransactions;
    private BellGateway $bellGateway;

    public function _before()
    {
        $this->bellTransactions = $this->tester->get(BellTransactions::class);
        $this->bellGateway = $this->tester->get(BellGateway::class);

        $this->tester->clearTable('fs_bell');
        $this->tester->clearTable('fs_foodsaver_has_bell');
    }

    public function testMissingEntityIdOnRemoveOfGroupedBellEvent()
    {
        // ## Preconditions
        // - User exists
        $user1 = $this->tester->createFoodsaver();

        // - Event exists with identifier of group bell but without group required field
        $bellData = Bell::create(
            'first bell title',
            'Any text',
            '',
            ['href' => 'Event1'],
            ['noEntityId' => null], // entityId is missing
            '',
            true,
        );
        // - User is linked with bell
        $this->bellGateway->addBell([$user1['id']], $bellData);

        // ## when
        $this->bellTransactions->removeGroupedBellEvent([$user1['id']], $bellData, 1);

        // ## then
        // - User have no events listed
        $this->tester->seeNumRecords(1, 'fs_foodsaver_has_bell', ['foodsaver_id' => $user1['id']]);
    }
}
