<?php

declare(strict_types=1);

namespace Tests\Unit;

use Foodsharing\Modules\Store\DTO\RegularPickup;
use Foodsharing\Modules\Store\PickupGateway;
use Foodsharing\Modules\Store\PickupTransactions;
use Foodsharing\Modules\Store\PickupValidationException;
use Foodsharing\Modules\Store\RegularPickupGateway;
use Foodsharing\Modules\Store\StoreTransactions;
use PHPUnit\Framework\TestCase;

class PickupTransactionsTest extends TestCase
{
    private PickupTransactions $pickupTransactions;
    private StoreTransactions $storeTransactions;

    private RegularPickupGateway $regularPickupGateway;

    protected function setUp(): void
    {
        $this->oneTimePickupGateway = $this->createMock(PickupGateway::class);
        $this->regularPickupGateway = $this->createMock(RegularPickupGateway::class);
        $this->storeTransactions = $this->createMock(StoreTransactions::class);
        $this->pickupTransactions = new PickupTransactions($this->storeTransactions, $this->regularPickupGateway, $this->oneTimePickupGateway);
    }

    public function testListRegularPickups(): void
    {
        $pickup_1 = new RegularPickup();
        $pickup_1->weekday = 4;
        $pickup_1->startTimeOfPickup = '16:40:00';
        $pickup_1->maxCountOfSlots = 2;
        $storeId = 1;

        $this->regularPickupGateway->method('getRegularPickup')->with($this->equalTo($storeId))->willReturn([$pickup_1]);
        $this->assertEquals(
            [$pickup_1],
            $this->pickupTransactions->getRegularPickup($storeId)
        );
    }

    public function testReplaceRegularCheckPickup(): void
    {
        $storeId = 1;
        $pickup_1 = new RegularPickup();
        $pickup_1->weekday = 4;
        $pickup_1->startTimeOfPickup = '16:40:00';
        $pickup_1->maxCountOfSlots = 2;

        $this->storeTransactions->expects($this->exactly(1))->method('existStore')->with($this->equalTo($storeId))->willReturn(true);
        $this->regularPickupGateway->expects($this->exactly(1))->method('deleteAllRegularPickups')->with($this->equalTo($storeId))->willReturn(1);
        $matcher = $this->exactly(1);
        $this->regularPickupGateway->expects($matcher)->method('insertOrUpdateRegularPickup')->willReturnCallback(
            fn () => match ($matcher->numberOfInvocations()) {
                1 => [$this->equalTo($storeId), $this->equalTo($pickup_1)],
            }
        )->willReturnOnConsecutiveCalls(9000);
        $this->storeTransactions->expects($this->exactly(1))->method('triggerBellForRegularPickupChanged')->with($this->equalTo($storeId));
        $this->regularPickupGateway->expects($this->exactly(1))->method('getRegularPickup')->with($this->equalTo($storeId))->willReturn([$pickup_1]);
        $reloaded = $this->pickupTransactions->replaceRegularPickup($storeId, [$pickup_1]);

        $this->storeTransactions->method('triggerBellForRegularPickupChanged')->with($this->equalTo($storeId));

        $this->assertEquals(
            $pickup_1,
            $reloaded[0]
        );
    }

    public function testReplaceRegularCheckTwoPickup(): void
    {
        $storeId = 1;
        $pickup_1 = new RegularPickup();
        $pickup_1->weekday = 4;
        $pickup_1->startTimeOfPickup = '16:40:00';
        $pickup_1->maxCountOfSlots = 2;

        $pickup_2 = new RegularPickup();
        $pickup_2->weekday = 3;
        $pickup_2->startTimeOfPickup = '16:40:00';
        $pickup_2->maxCountOfSlots = 4;

        $this->storeTransactions->expects($this->exactly(1))->method('existStore')->with($this->equalTo($storeId))->willReturn(true);
        $this->regularPickupGateway->expects($this->exactly(1))->method('deleteAllRegularPickups')->with($this->equalTo($storeId))->willReturn(2);
        $matcher = $this->exactly(2);
        $this->regularPickupGateway->expects($matcher)->method('insertOrUpdateRegularPickup')->willReturnCallback(fn () => match ($matcher->numberOfInvocations()) {
            1 => [$this->equalTo($storeId), $this->equalTo($pickup_1)],
            2 => [$this->equalTo($storeId), $this->equalTo($pickup_2)],
        })->willReturnOnConsecutiveCalls(9000, 100);
        $this->storeTransactions->expects($this->exactly(1))->method('triggerBellForRegularPickupChanged')->with($this->equalTo($storeId));
        $this->regularPickupGateway->expects($this->exactly(1))->method('getRegularPickup')->with($this->equalTo($storeId))->willReturn([$pickup_2, $pickup_1]);

        $reloaded = $this->pickupTransactions->replaceRegularPickup($storeId, [$pickup_1, $pickup_2]);
        $this->assertEquals(
            $pickup_2,
            $reloaded[0]
        );
        $this->assertEquals(
            $pickup_1,
            $reloaded[1]
        );
    }

    public function testReplaceRegularCheckTwoPickupWithOneOld(): void
    {
        $storeId = 1;
        $pickup_1 = new RegularPickup();
        $pickup_1->weekday = 4;
        $pickup_1->startTimeOfPickup = '16:40:00';
        $pickup_1->maxCountOfSlots = 2;

        $pickup_2 = new RegularPickup();
        $pickup_2->weekday = 3;
        $pickup_2->startTimeOfPickup = '16:40:00';
        $pickup_2->maxCountOfSlots = 4;

        $this->storeTransactions->expects($this->exactly(1))->method('existStore')->with($this->equalTo($storeId))->willReturn(true);
        $matcher = $this->exactly(2);
        $this->regularPickupGateway->expects($matcher)->method('insertOrUpdateRegularPickup')->willReturnCallback(fn () => match ($matcher->numberOfInvocations()) {
            1 => [$this->equalTo($storeId), $this->equalTo($pickup_1)],
            2 => [$this->equalTo($storeId), $this->equalTo($pickup_2)],
        })->willReturnOnConsecutiveCalls(9000, 250);
        $this->storeTransactions->expects($this->exactly(1))->method('triggerBellForRegularPickupChanged')->with($this->equalTo($storeId));
        $this->regularPickupGateway->expects($this->exactly(1))->method('getRegularPickup')->with($this->equalTo($storeId))->willReturn([$pickup_2, $pickup_1]);

        $reloaded = $this->pickupTransactions->replaceRegularPickup($storeId, [$pickup_1, $pickup_2]);
        $this->assertEquals(
            $pickup_2,
            $reloaded[0]
        );
        $this->assertEquals(
            $pickup_1,
            $reloaded[1]
        );
    }

    public function testExceptionOnRegularCheckPickupWhenMultiplyPickupsHaveSameTime(): void
    {
        $storeId = 1;
        $pickup_1 = new RegularPickup();
        $pickup_1->weekday = 4;
        $pickup_1->startTimeOfPickup = '16:40:00';
        $pickup_1->maxCountOfSlots = 2;

        $pickup_2 = new RegularPickup();
        $pickup_2->weekday = 4;
        $pickup_2->startTimeOfPickup = '16:40:00';
        $pickup_2->maxCountOfSlots = 4;

        $this->storeTransactions->expects($this->atLeast(1))->method('existStore')->with($this->equalTo($storeId))->willReturn(true);
        $this->regularPickupGateway->expects($this->exactly(0))->method('insertOrUpdateRegularPickup');
        $this->storeTransactions->expects($this->exactly(0))->method('triggerBellForRegularPickupChanged');
        $this->regularPickupGateway->expects($this->exactly(0))->method('getRegularPickup');

        $this->expectException(PickupValidationException::class);
        $this->expectExceptionMessage(PickupValidationException::DUPLICATE_PICKUP_DAY_TIME);
        $this->pickupTransactions->replaceRegularPickup($storeId, [$pickup_1, $pickup_2]);
    }

    public function testExceptionOnRegularCheckPickupWhenSlotCountIsLargerThenMaximum(): void
    {
        $storeId = 1;
        $pickup_1 = new RegularPickup();
        $pickup_1->weekday = 4;
        $pickup_1->startTimeOfPickup = '16:40:00';
        $pickup_1->maxCountOfSlots = StoreTransactions::MAX_SLOTS_PER_PICKUP + 1;

        $this->storeTransactions->expects($this->atLeast(1))->method('existStore')->with($this->equalTo($storeId))->willReturn(true);
        $this->regularPickupGateway->expects($this->exactly(0))->method('insertOrUpdateRegularPickup');
        $this->storeTransactions->expects($this->exactly(0))->method('triggerBellForRegularPickupChanged');
        $this->regularPickupGateway->expects($this->exactly(0))->method('getRegularPickup');

        $this->expectException(PickupValidationException::class);
        $this->expectExceptionMessage(PickupValidationException::MAX_SLOT_COUNT_OUT_OF_RANGE);
        $this->pickupTransactions->replaceRegularPickup($storeId, [$pickup_1]);
    }

    public function testExceptionOnRegularCheckPickupWhenSlotCountIsLowerZero(): void
    {
        $storeId = 1;
        $pickup_1 = new RegularPickup();
        $pickup_1->weekday = 4;
        $pickup_1->startTimeOfPickup = '16:40:00';
        $pickup_1->maxCountOfSlots = StoreTransactions::MAX_SLOTS_PER_PICKUP + 1;

        $this->storeTransactions->expects($this->atLeast(1))->method('existStore')->with($this->equalTo($storeId))->willReturn(true);
        $this->regularPickupGateway->expects($this->exactly(0))->method('insertOrUpdateRegularPickup');
        $this->storeTransactions->expects($this->exactly(0))->method('triggerBellForRegularPickupChanged');
        $this->regularPickupGateway->expects($this->exactly(0))->method('getRegularPickup');

        $this->expectException(PickupValidationException::class);
        $this->expectExceptionMessage(PickupValidationException::MAX_SLOT_COUNT_OUT_OF_RANGE);
        $this->pickupTransactions->replaceRegularPickup($storeId, [$pickup_1]);
    }

    public function testRegularPickupWithInvalidStoreId(): void
    {
        $storeId = 500;
        $pickup_1 = new RegularPickup();
        $pickup_1->weekday = 4;
        $pickup_1->startTimeOfPickup = '16:40:00';
        $pickup_1->maxCountOfSlots = 2;

        $this->storeTransactions->expects($this->atLeast(1))->method('existStore')->with($this->equalTo($storeId))->willReturn(false);
        $this->regularPickupGateway->expects($this->exactly(0))->method('insertOrUpdateRegularPickup');
        $this->storeTransactions->expects($this->exactly(0))->method('triggerBellForRegularPickupChanged');
        $this->regularPickupGateway->expects($this->exactly(0))->method('getRegularPickup');

        $this->expectException(PickupValidationException::class);
        $this->expectExceptionMessage(PickupValidationException::INVALID_STORE);
        $this->pickupTransactions->replaceRegularPickup($storeId, [$pickup_1]);
    }
}
