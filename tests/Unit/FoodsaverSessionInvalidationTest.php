<?php

declare(strict_types=1);

namespace Tests\Unit;

use Codeception\Test\Unit;
use Foodsharing\Lib\Db\Mem;
use Foodsharing\Modules\Core\DBConstants\Foodsaver\Role;
use Foodsharing\Modules\Foodsaver\FoodsaverTransactions;
use Tests\Support\UnitTester;

class FoodsaverSessionInvalidationTest extends Unit
{
    protected UnitTester $tester;
    private FoodsaverTransactions $transactions;
    private Mem $mem;
    private array $fs;
    private string $sessId;

    protected function _before(): void
    {
        $this->transactions = $this->tester->get(FoodsaverTransactions::class);
        $this->fs = $this->tester->createFoodsaver();
        $this->mem = $this->tester->get(Mem::class);
        $this->mem->ensureConnected();
        $this->sessId = 'test-session';
        session_id($this->sessId);
        $this->mem->userAddSession($this->fs['id'], $this->sessId);
        $this->mem->cache->set('fs_sess:' . $this->sessId, 'dummy');
    }

    private function checkSession(bool $exists): void
    {
        if (!$exists) {
            // The session should be removed
            $this->assertSame(0, $this->mem->cache->exists('fs_sess:' . $this->sessId));
            $this->assertEmpty($this->mem->cache->sMembers('php:user:' . $this->fs['id'] . ':sessions'));
        } else {
            // The session should exist
            $this->assertSame(1, $this->mem->cache->exists('fs_sess:' . $this->sessId));
            $sessions = $this->mem->cache->sMembers('php:user:' . $this->fs['id'] . ':sessions');
            $this->assertContains($this->sessId, $sessions);
        }
    }

    public function testChangeUserDeverificationInvalidatesSessions(): void
    {
        // Ensure user is currently verified
        $this->tester->updateInDatabase('fs_foodsaver', ['verified' => 1], ['id' => $this->fs['id']]);

        // The session should exist before de-verification
        $this->checkSession(true);

        // Perform de-verification step
        $this->transactions->changeUserVerification($this->fs['id'], $this->fs['id'], false);

        // The sessions should be removed
        $this->checkSession(false);
    }

    public function testDowngradePermanentlyInvalidatesSessions(): void
    {
        // Ensure role is higher than FOODSHARER so downgrade actually updates
        $this->tester->updateInDatabase('fs_foodsaver', ['rolle' => Role::AMBASSADOR->value], ['id' => $this->fs['id']]);

        // The session should exist before permanent downgrade
        $this->checkSession(true);

        // Downgrade permanently to FOODSHARER
        $this->transactions->downgradePermanently($this->fs['id']);

        // The sessions should be removed
        $this->checkSession(false);
    }
}
