<?php

declare(strict_types=1);

namespace Tests\Unit;

use Carbon\Carbon;
use Codeception\Test\Unit;
use Foodsharing\Modules\Achievement\AchievementGateway;
use Foodsharing\Modules\Achievement\AchievementTransactions;
use Foodsharing\Modules\Achievement\DTO\Achievement;
use Foodsharing\Modules\Achievement\DTO\AwardedAchievement;
use Foodsharing\Modules\Core\DBConstants\Achievement\DuplicateMode;
use Foodsharing\Modules\Core\DBConstants\Achievement\VisibilityType;
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

        $this->initialAchievement = Achievement::create(
            id: null,
            regionId: 0,
            name: 'Some name',
            description: 'Some description',
            icon: 'icon',
            validityInDaysAfterAssignment: 365,
            createdAt: null,
            updatedAt: null,
            visibilityType: VisibilityType::PRIVATE,
            duplicateMode: DuplicateMode::OVERRIDE,
        );

        $this->user = $this->tester->createFoodsharer();
        $this->otherUser = $this->tester->createFoodsharer();
    }

    private function initRegions(): void
    {
        $this->region = $this->tester->createRegion('Parent');
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
        $this->assertEqualsWithDelta($retrievedAchievement->createdAt->getTimestamp(), time(), 1);
        $this->assertEquals($retrievedAchievement->updatedAt, null);

        $changedAchievement = clone $this->initialAchievement;
        $changedAchievement->id = $id;
        $changedAchievement->regionId = $this->region['id'];
        $changedAchievement->name = 'changed name';
        $changedAchievement->description = 'changed description';
        $changedAchievement->icon = 'changed icon';
        $changedAchievement->validityInDaysAfterAssignment = null;
        $this->gateway->updateAchievement($changedAchievement);

        $retrievedAchievementAfterUpdate = $this->gateway->getAchievement($id);
        $this->assertEquals($retrievedAchievementAfterUpdate->id, $id);
        $this->assertEquals($retrievedAchievementAfterUpdate->regionId, $changedAchievement->regionId);
        $this->assertEquals($retrievedAchievementAfterUpdate->name, $changedAchievement->name);
        $this->assertEquals($retrievedAchievementAfterUpdate->description, $changedAchievement->description);
        $this->assertEquals($retrievedAchievementAfterUpdate->icon, $changedAchievement->icon);
        $this->assertEquals($retrievedAchievementAfterUpdate->validityInDaysAfterAssignment, $changedAchievement->validityInDaysAfterAssignment);
        $this->assertEquals($retrievedAchievementAfterUpdate->createdAt->getTimestamp(), $retrievedAchievement->createdAt->getTimestamp());
        $this->assertEqualsWithDelta($retrievedAchievementAfterUpdate->updatedAt->getTimestamp(), time(), 1);
    }

    public function testAwardingAchievements(): void
    {
        $achievementId = $this->gateway->addAchievement($this->initialAchievement);
        $awardedAchievement = AwardedAchievement::create(
            0,
            $this->user['id'],
            $achievementId,
            reviewerId: $this->otherUser['id'],
            notice: 'Some notice'
        );

        $this->gateway->awardAchievement($awardedAchievement);
        $this->assertEquals(true, $this->gateway->hasAchievement($this->user['id'], $achievementId));
    }

    public function testOutdatedAchievement(): void
    {
        $achievementId = $this->gateway->addAchievement($this->initialAchievement);
        $awardedAchievement = AwardedAchievement::create(
            0,
            $this->user['id'],
            $achievementId,
            reviewerId: $this->otherUser['id'],
            notice: 'Some notice',
            validUntil: Carbon::now()->subDay()
        );

        $this->gateway->awardAchievement($awardedAchievement);
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

    public function testVisibilityTypes(): void
    {
        $this->initRegions();

        // create achievements with each visibility type in the same region
        $visibilities = [
            VisibilityType::HIDDEN,
            VisibilityType::PRIVATE,
            VisibilityType::STORE_MANAGERS,
            VisibilityType::SCOPE,
            VisibilityType::GLOBAL,
        ];

        $achievementIds = [];
        $this->initialAchievement->regionId = $this->region['id'];
        foreach ($visibilities as $vis) {
            $ach = clone $this->initialAchievement;
            $ach->visibilityType = $vis;
            $achievementIds[(int)$vis->value] = $this->gateway->addAchievement($ach);
        }

        // award all achievements to the user
        foreach ($achievementIds as $id) {
            $this->transactions->awardAchievementFromId($id, $this->user['id']);
        }

        // Helper: extract visibility type values from returned awarded achievements
        $extractVisValues = function (array $awarded) {
            return array_values(array_map(fn ($a) => $a->visibilityType->value, $awarded));
        };

        // 1) ORGA sees everything (including HIDDEN)
        $orgaSession = $this->createMock(\Foodsharing\Lib\Session::class);
        $orgaSession->method('mayRole')->willReturnMap([
            [\Foodsharing\Modules\Core\DBConstants\Foodsaver\Role::ORGA, true],
            [\Foodsharing\Modules\Core\DBConstants\Foodsaver\Role::STORE_MANAGER, true],
        ]);
        $orgaSession->method('id')->willReturn(9999); // not the owner
        $orgaUnits = $this->createMock(\Foodsharing\Modules\Unit\CurrentUserUnitsInterface::class);

        $transactionsOrga = new AchievementTransactions($this->gateway, $orgaUnits);
        $visibleOrga = $transactionsOrga->getVisibleAwardedAchievementsForUser($this->user['id'], $orgaSession);
        $this->assertEqualsCanonicalizing(
            array_map(fn ($v) => $v->value, $visibilities),
            $extractVisValues($visibleOrga)
        );

        // 2) Owner (the awarded user) sees everything except HIDDEN
        $ownerSession = $this->createMock(\Foodsharing\Lib\Session::class);
        $ownerSession->method('mayRole')->willReturn(false);
        $ownerSession->method('id')->willReturn($this->user['id']);
        $ownerUnits = $this->createMock(\Foodsharing\Modules\Unit\CurrentUserUnitsInterface::class);
        $ownerUnits->method('isAdminFor')->willReturn(false);
        $ownerUnits->method('mayBezirk')->willReturn(false); // asume owner is not part of the scope

        $transactionsOwner = new AchievementTransactions($this->gateway, $ownerUnits);
        $visibleOwner = $transactionsOwner->getVisibleAwardedAchievementsForUser($this->user['id'], $ownerSession);
        $this->assertEqualsCanonicalizing(
            array_map(fn ($v) => $v->value, array_filter($visibilities, fn ($v) => $v !== VisibilityType::HIDDEN)),
            $extractVisValues($visibleOwner)
        );

        // 3) Admin for region (not ORGA) sees PRIVATE, SCOPE and GLOBAL (but not STORE_MANAGERS unless also STORE_MANAGER)
        $adminSession = $this->createMock(\Foodsharing\Lib\Session::class);
        $adminSession->method('mayRole')->willReturnMap([
            [\Foodsharing\Modules\Core\DBConstants\Foodsaver\Role::ORGA, false],
            [\Foodsharing\Modules\Core\DBConstants\Foodsaver\Role::STORE_MANAGER, false],
        ]);
        $adminSession->method('id')->willReturn(5555);

        $adminUnits = $this->createMock(\Foodsharing\Modules\Unit\CurrentUserUnitsInterface::class);
        $adminUnits->method('isAdminFor')->willReturn(true);
        $adminUnits->method('mayBezirk')->willReturn(true);

        $txAdmin = new AchievementTransactions($this->gateway, $adminUnits);
        $visibleAdmin = $txAdmin->getVisibleAwardedAchievementsForUser($this->user['id'], $adminSession);
        $expectedAdmin = [
            VisibilityType::PRIVATE->value,
            VisibilityType::SCOPE->value,
            VisibilityType::GLOBAL->value,
        ];
        $this->assertEqualsCanonicalizing($expectedAdmin, $extractVisValues($visibleAdmin));

        // 4) Store manager in scope sees STORE_MANAGERS, SCOPE and GLOBAL
        $smSession = $this->createMock(\Foodsharing\Lib\Session::class);
        $smSession->method('mayRole')->willReturnMap([
            [\Foodsharing\Modules\Core\DBConstants\Foodsaver\Role::ORGA, false],
            [\Foodsharing\Modules\Core\DBConstants\Foodsaver\Role::STORE_MANAGER, true],
        ]);
        $smSession->method('id')->willReturn(7777);

        $smUnits = $this->createMock(\Foodsharing\Modules\Unit\CurrentUserUnitsInterface::class);
        $smUnits->method('isAdminFor')->willReturn(false);
        $smUnits->method('mayBezirk')->willReturn(true);

        $txSM = new AchievementTransactions($this->gateway, $smUnits);
        $visibleSM = $txSM->getVisibleAwardedAchievementsForUser($this->user['id'], $smSession);
        $expectedSM = [
            VisibilityType::STORE_MANAGERS->value,
            VisibilityType::SCOPE->value,
            VisibilityType::GLOBAL->value,
        ];
        $this->assertEqualsCanonicalizing($expectedSM, $extractVisValues($visibleSM));

        // 5) Unrelated user (no roles, not in scope) sees only GLOBAL
        $otherSession = $this->createMock(\Foodsharing\Lib\Session::class);
        $otherSession->method('mayRole')->willReturn(false);
        $otherSession->method('id')->willReturn($this->otherUser['id']);

        $otherUnits = $this->createMock(\Foodsharing\Modules\Unit\CurrentUserUnitsInterface::class);
        $otherUnits->method('isAdminFor')->willReturn(false);
        $otherUnits->method('mayBezirk')->willReturn(false);

        $txOther = new AchievementTransactions($this->gateway, $otherUnits);
        $visibleOther = $txOther->getVisibleAwardedAchievementsForUser($this->user['id'], $otherSession);
        $this->assertEquals([VisibilityType::GLOBAL->value], $extractVisValues($visibleOther));
    }

    public function testDuplicateModes(): void
    {
        // OVERRIDE duplicate mode: awarding twice should update the existing award instead of creating a new one
        $this->initialAchievement->duplicateMode = DuplicateMode::OVERRIDE;
        $achievementOverrideId = $this->gateway->addAchievement($this->initialAchievement);

        $awarded1 = AwardedAchievement::create(
            0,
            $this->user['id'],
            $achievementOverrideId,
            reviewerId: $this->otherUser['id'],
            notice: 'first override notice',
        );

        $id1 = $this->transactions->awardAchievement($awarded1);

        $awarded2 = clone $awarded1;
        $awarded2->notice = 'updated override notice';
        $id2 = $this->transactions->awardAchievement($awarded2);

        $awardedListOverride = $this->gateway->getAwardedUsersForAchievement($achievementOverrideId);
        $this->assertCount(1, $awardedListOverride);
        $this->assertEquals($id1, $id2);

        $retrieved = $this->gateway->getAwardedAchievementForUser($achievementOverrideId, $this->user['id']);
        $this->assertEquals('updated override notice', $retrieved->notice);

        // MULTIPLE duplicate mode: awarding twice creates two records
        $this->initialAchievement->duplicateMode = DuplicateMode::MULTIPLE;
        $achievementId = $this->gateway->addAchievement($this->initialAchievement);

        $awarded1 = AwardedAchievement::create(
            0,
            $this->user['id'],
            $achievementId,
            reviewerId: $this->otherUser['id'],
            notice: 'first notice',
        );

        $id1 = $this->transactions->awardAchievement($awarded1);

        $awarded2 = clone $awarded1;
        $awarded2->notice = 'second notice';
        $id2 = $this->transactions->awardAchievement($awarded2);

        $awardedList = $this->gateway->getAwardedUsersForAchievement($achievementId);
        $this->assertCount(2, $awardedList);
        $this->assertNotEquals($id1, $id2);
    }
}
