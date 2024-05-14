<?php

declare(strict_types=1);

namespace Tests\Unit;

use Carbon\Carbon;
use Codeception\Test\Unit;
use Foodsharing\Modules\Achievement\AchievementGateway;
use Foodsharing\Modules\Achievement\AchievementTransactions;
use Foodsharing\Modules\Achievement\DTO\Achievement;
use Foodsharing\Modules\Achievement\DTO\AwardedAchievement;
use Tests\Support\UnitTester;

class AchievementGatewayTest extends Unit
{
    protected UnitTester $tester;
    private AchievementGateway $gateway;
    private AchievementTransactions $transactions;
    private Achievement $initialAchievement;
    private array $user;
    private array $otherUser;

    final public function _before(): void
    {
        $this->gateway = $this->tester->get(AchievementGateway::class);
        $this->transactions = $this->tester->get(AchievementTransactions::class);

        $this->initialAchievement = new Achievement();
        $this->initialAchievement->name = 'Some name';
        $this->initialAchievement->description = 'Some description';
        $this->initialAchievement->validityInDaysAfterAssignment = 365;
        $this->initialAchievement->isRequestableByFoodsaver = true;

        $this->user = $this->tester->createFoodsharer();
        $this->otherUser = $this->tester->createFoodsharer();
    }

    public function testAchievementGetterSetter(): void
    {
        $id = $this->gateway->addAchievement($this->initialAchievement);
        $this->assertNotEquals($id, 0);

        $retrievedAchievement = $this->gateway->getAchievement($id);
        $this->assertEquals($retrievedAchievement->id, $id);
        $this->assertEquals($retrievedAchievement->name, $this->initialAchievement->name);
        $this->assertEquals($retrievedAchievement->description, $this->initialAchievement->description);
        $this->assertEquals($retrievedAchievement->validityInDaysAfterAssignment, $this->initialAchievement->validityInDaysAfterAssignment);
        $this->assertEquals($retrievedAchievement->isRequestableByFoodsaver, $this->initialAchievement->isRequestableByFoodsaver);
        $this->assertEqualsWithDelta($retrievedAchievement->createdAt->getTimestamp(), time(), 1);
        $this->assertEquals($retrievedAchievement->updatedAt, null);

        $changedAchievement = clone $this->initialAchievement;
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

    public function testAwardingAchievements(): void
    {
        $achievementId = $this->gateway->addAchievement($this->initialAchievement);
        $awardedAchievement = new AwardedAchievement();
        $awardedAchievement->foodsaverId = $this->user['id'];
        $awardedAchievement->achievementId = $achievementId;
        $awardedAchievement->reviewerId = $this->otherUser['id'];
        $awardedAchievement->notice = 'Some notice';

        $this->transactions->awardAchievement($awardedAchievement);
        $this->assertEquals(true, $this->gateway->hasAchievement($this->user['id'], $achievementId));
    }

    public function testOutdatedAchievement(): void
    {
        $achievementId = $this->gateway->addAchievement($this->initialAchievement);
        $awardedAchievement = new AwardedAchievement();
        $awardedAchievement->foodsaverId = $this->user['id'];
        $awardedAchievement->achievementId = $achievementId;
        $awardedAchievement->reviewerId = $this->otherUser['id'];
        $awardedAchievement->notice = 'Some notice';
        $awardedAchievement->validUntil = Carbon::now()->subDay();

        $this->transactions->awardAchievement($awardedAchievement);
        $this->assertEquals(false, $this->gateway->hasAchievement($this->user['id'], $achievementId));
    }
}
