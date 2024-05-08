<?php

declare(strict_types=1);

namespace Tests\Unit;

use Codeception\Test\Unit;
use Foodsharing\Modules\Achievement\AchievementGateway;
use Foodsharing\Modules\Achievement\DTO\Achievement;
use Tests\Support\UnitTester;

class AchievementGatewayTest extends Unit
{
    protected UnitTester $tester;
    private AchievementGateway $gateway;

    final public function _before(): void
    {
        $this->gateway = $this->tester->get(AchievementGateway::class);
    }

    public function testAchievementGetterSetter(): void
    {
        $initialAchievement = new Achievement();
        $initialAchievement->name = 'Some name';
        $initialAchievement->description = 'Some description';
        $initialAchievement->validityInDaysAfterAssignment = 365;
        $initialAchievement->isRequestableByFoodsaver = true;

        $id = $this->gateway->addAchievement($initialAchievement);
        $this->assertNotEquals($id, 0);

        $retrievedAchievement = $this->gateway->getAchievement($id);
        $this->assertEquals($retrievedAchievement->id, $id);
        $this->assertEquals($retrievedAchievement->name, $initialAchievement->name);
        $this->assertEquals($retrievedAchievement->description, $initialAchievement->description);
        $this->assertEquals($retrievedAchievement->validityInDaysAfterAssignment, $initialAchievement->validityInDaysAfterAssignment);
        $this->assertEquals($retrievedAchievement->isRequestableByFoodsaver, $initialAchievement->isRequestableByFoodsaver);
        $this->assertEqualsWithDelta($retrievedAchievement->createdAt->getTimestamp(), time(), 1);
        $this->assertEquals($retrievedAchievement->updatedAt, null);

        $changedAchievement = clone $initialAchievement;
        $changedAchievement->id = $id;
        $changedAchievement->name = 'changed name';
        $changedAchievement->name = 'changed description';
        $changedAchievement->validityInDaysAfterAssignment = null;
        $changedAchievement->isRequestableByFoodsaver = false;
        $this->gateway->updateAchievement($changedAchievement);

        $retrievedAchievementAfterUpdate = $this->gateway->getAchievement($id);
        $this->assertEquals($retrievedAchievementAfterUpdate->id, $id);
        $this->assertEquals($retrievedAchievementAfterUpdate->name, $changedAchievement->name);
        $this->assertEquals($retrievedAchievementAfterUpdate->description, $changedAchievement->description);
        $this->assertEquals($retrievedAchievementAfterUpdate->validityInDaysAfterAssignment, $changedAchievement->validityInDaysAfterAssignment);
        $this->assertEquals($retrievedAchievementAfterUpdate->isRequestableByFoodsaver, $changedAchievement->isRequestableByFoodsaver);
        $this->assertEquals($retrievedAchievementAfterUpdate->createdAt->getTimestamp(), $retrievedAchievement->createdAt->getTimestamp());
        $this->assertEqualsWithDelta($retrievedAchievementAfterUpdate->updatedAt->getTimestamp(), time(), 1);
    }
}
