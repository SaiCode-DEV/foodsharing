<?php

declare(strict_types=1);

namespace Tests\Unit;

use Codeception\Test\Unit;
use Foodsharing\Modules\Core\Database;
use Foodsharing\Modules\Group\GroupGateway;
use Tests\Support\UnitTester;

class GroupGatewayTest extends Unit
{
    protected UnitTester $tester;
    private GroupGateway $gateway;
    private Database $db;

    protected function _before(): void
    {
        $this->gateway = $this->tester->get(GroupGateway::class);
        $this->db = $this->tester->get(Database::class);
    }

    public function testDeleteGroupRollsBackWhenTheRegionCannotBeDeleted(): void
    {
        // #2018: deleting a region removes its events before the region row itself.
        // A store holds the region row back (its foreign key is RESTRICT). The checks
        // go through the gateway's own connection: a failed run that never rolls back
        // leaves it inside the aborted transaction, where the events are already gone.
        $region = $this->tester->createRegion();
        $foodsaver = $this->tester->createFoodsaver(null, ['bezirk_id' => $region['id']]);
        $event = $this->tester->createEvents($region['id'], $foodsaver['id']);
        $this->tester->createStore($region['id']);

        $failed = false;
        try {
            $this->gateway->deleteGroup($region['id']);
        } catch (\Throwable) {
            $failed = true;
        }

        $this->assertTrue($failed, 'deleting a region that still has a store must not succeed');
        $this->assertTrue($this->db->exists('fs_bezirk', ['id' => $region['id']]));
        $this->assertTrue($this->db->exists('fs_event', ['id' => $event['id']]));
        $this->assertTrue($this->db->exists('fs_foodsaver', ['id' => $foodsaver['id'], 'bezirk_id' => $region['id']]));
    }
}
