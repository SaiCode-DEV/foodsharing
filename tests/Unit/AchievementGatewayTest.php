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
    private $region;
    private $childRegion;

    final public function _before(): void
    {
        $this->gateway = $this->tester->get(AchievementGateway::class);
        $this->transactions = $this->tester->get(AchievementTransactions::class);

        $this->initialAchievement = new Achievement();
        $this->initialAchievement->regionId = 0;
        $this->initialAchievement->name = 'Some name';
        $this->initialAchievement->description = 'Some description';
        $this->initialAchievement->icon = 'icon';
        $this->initialAchievement->validityInDaysAfterAssignment = 365;
        $this->initialAchievement->isRequestableByFoodsaver = true;

        $this->user = $this->tester->createFoodsharer();
        $this->otherUser = $this->tester->createFoodsharer();
    }

    private function initRegions(): void
    {
        $this->region = $this->tester->createRegion('Parent', fillMailbox: false);
        $this->childRegion = $this->tester->createRegion('Child', ['parent_id' => $this->region['id']], false);
        $this->tester->addRegionMember($this->region['id'], $this->user['id']);
        $this->tester->addRegionMember($this->childRegion['id'], $this->user['id']);
    }

    public function testAchievementGetterSetter(): void
    {
        $this->initRegions();
        $id = $this->gateway->addAchievement($this->initialAchievement);
        $this->assertNotEquals($id, 0);

        $retrievedAchievement = $this->gateway->getAchievement($id);
        $this->assertEquals($retrievedAchievement->id, $id);
        $this->assertEquals($retrievedAchievement->regionId, $this->initialAchievement->regionId);
        $this->assertEquals($retrievedAchievement->name, $this->initialAchievement->name);
        $this->assertEquals($retrievedAchievement->description, $this->initialAchievement->description);
        $this->assertEquals($retrievedAchievement->icon, $this->initialAchievement->icon);
        $this->assertEquals($retrievedAchievement->validityInDaysAfterAssignment, $this->initialAchievement->validityInDaysAfterAssignment);
        $this->assertEquals($retrievedAchievement->isRequestableByFoodsaver, $this->initialAchievement->isRequestableByFoodsaver);
        $this->assertEqualsWithDelta($retrievedAchievement->createdAt->getTimestamp(), time(), 1);
        $this->assertEquals($retrievedAchievement->updatedAt, null);

        $changedAchievement = clone $this->initialAchievement;
        $changedAchievement->id = $id;
        $changedAchievement->regionId = $this->region['id'];
        $changedAchievement->name = 'changed name';
        $changedAchievement->description = 'changed description';
        $changedAchievement->icon = 'changed icon';
        $changedAchievement->validityInDaysAfterAssignment = null;
        $changedAchievement->isRequestableByFoodsaver = false;
        $this->gateway->updateAchievement($changedAchievement);

        $retrievedAchievementAfterUpdate = $this->gateway->getAchievement($id);
        $this->assertEquals($retrievedAchievementAfterUpdate->id, $id);
        $this->assertEquals($retrievedAchievementAfterUpdate->regionId, $changedAchievement->regionId);
        $this->assertEquals($retrievedAchievementAfterUpdate->name, $changedAchievement->name);
        $this->assertEquals($retrievedAchievementAfterUpdate->description, $changedAchievement->description);
        $this->assertEquals($retrievedAchievementAfterUpdate->icon, $changedAchievement->icon);
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

    public function testGetAchievementsFromRegion(): void
    {
        $this->initRegions();

        $this->initialAchievement->regionId = $this->region['id'];
        $id1 = $this->gateway->addAchievement($this->initialAchievement);
        $id2 = $this->gateway->addAchievement($this->initialAchievement);
        $this->initialAchievement->regionId = $this->childRegion['id'];
        $id3 = $this->gateway->addAchievement($this->initialAchievement);

        $retrievedAchievements = $this->gateway->getAchievementsFromRegion($this->region['id']);
        $this->assertEqualsCanonicalizing(array_column($retrievedAchievements, 'id'), [$id1, $id2]);

        $retrievedAchievementsChild = $this->gateway->getAchievementsFromRegion($this->childRegion['id']);
        $this->assertEqualsCanonicalizing(array_column($retrievedAchievementsChild, 'id'), [$id3]);

        $retrievedAchievementsChild = $this->gateway->getAchievementsFromRegion(100);
        $this->assertEquals(array_column($retrievedAchievementsChild, 'id'), []);
    }

    public function testRegionHasAchievements(): void
    {
        $this->initRegions();

        $this->assertEquals($this->gateway->regionHasAchievements($this->region['id']), false);

        $this->gateway->addAchievement($this->initialAchievement);
        $this->assertEquals($this->gateway->regionHasAchievements($this->region['id']), false);

        $this->initialAchievement->regionId = $this->region['id'];
        $this->gateway->addAchievement($this->initialAchievement);
        $this->assertEquals($this->gateway->regionHasAchievements($this->region['id']), true);
        $this->assertEquals($this->gateway->regionHasAchievements($this->childRegion['id']), false);

        $this->gateway->addAchievement($this->initialAchievement);
        $this->assertEquals($this->gateway->regionHasAchievements($this->region['id']), true);
    }
}
