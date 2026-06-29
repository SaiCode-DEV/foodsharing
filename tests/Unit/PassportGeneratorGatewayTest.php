<?php

declare(strict_types=1);

namespace Tests\Unit;

use Codeception\Test\Unit;
use Foodsharing\Modules\PassportGenerator\PassportGeneratorGateway;
use Tests\Support\UnitTester;

class PassportGeneratorGatewayTest extends Unit
{
    protected UnitTester $tester;
    private PassportGeneratorGateway $gateway;

    public function _before()
    {
        $this->gateway = $this->tester->get(PassportGeneratorGateway::class);
    }

    /**
     * Regression for #1704: the fs_pass_gen primary key is (foodsaver_id, date)
     * with second precision, so generating a passport for the same foodsaver
     * twice within the same second used to raise a duplicate-key PDOException.
     * The second call must now be a silent no-op instead.
     */
    public function testLoggingPassGenerationTwiceInSameSecondDoesNotThrow(): void
    {
        $bot = $this->tester->createFoodsaver();
        $user = $this->tester->createFoodsaver();

        // Both calls run within the same second, so they produce the same
        // (foodsaver_id, date) primary key. Before the fix the second insert
        // crashed; now it is ignored.
        $this->gateway->logPassGeneration($bot['id'], [$user['id']]);
        $this->gateway->logPassGeneration($bot['id'], [$user['id']]);

        $this->tester->seeInDatabase('fs_pass_gen', [
            'foodsaver_id' => $user['id'],
            'bot_id' => $bot['id'],
        ]);
    }
}
