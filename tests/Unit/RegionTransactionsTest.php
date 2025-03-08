<?php

declare(strict_types=1);

namespace Tests\Unit;

use Foodsharing\Modules\Achievement\AchievementGateway;
use Foodsharing\Modules\Core\DBConstants\Unit\UnitType;
use Foodsharing\Modules\Foodsaver\FoodsaverGateway;
use Foodsharing\Modules\FoodSharePoint\FoodSharePointGateway;
use Foodsharing\Modules\Group\GroupFunctionGateway;
use Foodsharing\Modules\Mailbox\MailboxGateway;
use Foodsharing\Modules\Region\ForumFollowerGateway;
use Foodsharing\Modules\Region\RegionGateway;
use Foodsharing\Modules\Region\RegionTransactions;
use Foodsharing\Modules\Unit\CurrentUserUnitsInterface;
use Foodsharing\Modules\Unit\DTO\UserUnit;
use Foodsharing\Modules\Unit\UnitGateway;
use Foodsharing\Permissions\AchievementPermissions;
use Foodsharing\Permissions\FoodSharePointPermissions;
use Foodsharing\Permissions\RegionPermissions;
use Foodsharing\Permissions\ReportPermissions;
use Foodsharing\Permissions\VotingPermissions;
use Foodsharing\Permissions\WorkGroupPermissions;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Cache\CacheInterface;

class RegionTransactionsTest extends TestCase
{
    private RegionTransactions $regionTransactions;

    private FoodsaverGateway $foodsaverGateway;
    private UnitGateway $unitGateway;
    private RegionGateway $regionGateway;
    private GroupFunctionGateway $groupFunctionGateway;
    private MailboxGateway $mailboxGateway;
    private RegionPermissions $regionPermissions;
    private AchievementGateway $achievementGateway;
    protected $userUnitMock;
    private ReportPermissions $reportPermissions;
    private WorkGroupPermissions $workGroupPermissions;
    private FoodSharePointGateway $foodSharePointGateway;
    private FoodSharePointPermissions $foodSharePointPermissions;
    private VotingPermissions $votingPermissions;
    private AchievementPermissions $achievementPermissions;
    private ForumFollowerGateway $forumFollowerGateway;
    private CacheInterface $cache;

    protected function setUp(): void
    {
        $this->foodsaverGateway = $this->createMock(FoodsaverGateway::class);
        $this->unitGateway = $this->createMock(UnitGateway::class);
        $this->regionGateway = $this->createMock(RegionGateway::class);
        $this->groupFunctionGateway = $this->createMock(GroupFunctionGateway::class);
        $this->mailboxGateway = $this->createMock(MailboxGateway::class);
        $this->regionPermissions = $this->createMock(RegionPermissions::class);
        $this->achievementGateway = $this->createMock(AchievementGateway::class);
        $this->userUnitMock = $this->createMock(CurrentUserUnitsInterface::class);
        $this->reportPermissions = $this->createMock(ReportPermissions::class);
        $this->workGroupPermissions = $this->createMock(WorkGroupPermissions::class);
        $this->foodSharePointGateway = $this->createMock(FoodSharePointGateway::class);
        $this->foodSharePointPermissions = $this->createMock(FoodSharePointPermissions::class);
        $this->votingPermissions = $this->createMock(VotingPermissions::class);
        $this->achievementPermissions = $this->createMock(AchievementPermissions::class);
        $this->forumFollowerGateway = $this->createMock(ForumFollowerGateway::class);
        $this->cache = $this->createMock(CacheInterface::class);

        $this->regionTransactions = new RegionTransactions(
            $this->foodsaverGateway,
            $this->unitGateway,
            $this->regionGateway,
            $this->groupFunctionGateway,
            $this->mailboxGateway,
            $this->regionPermissions,
            $this->achievementGateway,
            $this->userUnitMock,
            $this->reportPermissions,
            $this->workGroupPermissions,
            $this->foodSharePointGateway,
            $this->foodSharePointPermissions,
            $this->votingPermissions,
            $this->achievementPermissions,
            $this->forumFollowerGateway,
            $this->cache
        );
    }

    public function testListFoodsaversRegionsEmpty(): void
    {
        $this->unitGateway->method('listAllDirectReleatedUnitsAndResponsibilitiesOfFoodsaver')->with($this->equalTo(1), UnitType::getRegionTypes())->willReturn([]);
        $this->assertEquals(
            [],
            $this->regionTransactions->getUserRegions(1)
        );
    }

    public function testListFoodsaversRegionsElement(): void
    {
        $units = [new UserUnit()];
        $this->unitGateway->method('listAllDirectReleatedUnitsAndResponsibilitiesOfFoodsaver')->with($this->equalTo(2), UnitType::getRegionTypes())->willReturn($units);
        $this->assertEquals(
            $units,
            $this->regionTransactions->getUserRegions(2)
        );
    }

    public function testNewFoodsaverVerified(): void
    {
        $this->assertSame(
            RegionTransactions::NEW_FOODSAVER_VERIFIED,
            $this->regionTransactions->getJoinMessage(1, true)
        );
    }

    public function testNewFoodsaverNeedsVerification(): void
    {
        $this->foodsaverGateway->method('foodsaverWasVerifiedBefore')->willReturn(true);
        $this->assertSame(
            RegionTransactions::NEW_FOODSAVER_NEEDS_VERIFICATION,
            $this->regionTransactions->getJoinMessage(1, false)
        );
    }

    public function testNewFoodsaverNeedsIntroduction(): void
    {
        $this->foodsaverGateway->method('foodsaverWasVerifiedBefore')->willReturn(false);
        $this->assertSame(
            RegionTransactions::NEW_FOODSAVER_NEEDS_INTRODUCTION,
            $this->regionTransactions->getJoinMessage(1, false)
        );
    }
}
