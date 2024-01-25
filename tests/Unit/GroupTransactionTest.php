<?php

declare(strict_types=1);

namespace Tests\Unit;

use Foodsharing\Modules\Core\DBConstants\Unit\UnitType;
use Foodsharing\Modules\Group\GroupGateway;
use Foodsharing\Modules\Group\GroupTransactions;
use Foodsharing\Modules\Unit\DTO\UserUnit;
use Foodsharing\Modules\Unit\UnitGateway;
use PHPUnit\Framework\TestCase;

class GroupTransactionsTest extends TestCase
{
    private GroupTransactions $groupTansactions;

    private GroupGateway $groupGateway;
    private UnitGateway $unitGateway;

    protected function setUp(): void
    {
        $this->groupGateway = $this->createMock(GroupGateway::class);
        $this->unitGateway = $this->createMock(UnitGateway::class);
        $this->groupTansactions = new GroupTransactions($this->groupGateway, $this->unitGateway);
    }

    public function testListFoodsaversRegionsEmpty(): void
    {
        $this->unitGateway->method('listAllDirectReleatedUnitsAndResponsibilitiesOfFoodsaver')->with($this->equalTo(1), UnitType::getGroupTypes())->willReturn([]);
        $this->assertEquals(
            [],
            $this->groupTansactions->getUserGroups(1)
        );
    }

    public function testListFoodsaversRegionsElement(): void
    {
        $units = [new UserUnit()];
        $this->unitGateway->method('listAllDirectReleatedUnitsAndResponsibilitiesOfFoodsaver')->with($this->equalTo(2), UnitType::getGroupTypes())->willReturn($units);
        $this->assertEquals(
            $units,
            $this->groupTansactions->getUserGroups(2)
        );
    }
}
