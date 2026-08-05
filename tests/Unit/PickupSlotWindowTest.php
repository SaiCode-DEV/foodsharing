<?php

declare(strict_types=1);

namespace Tests\Unit;

use Carbon\Carbon;
use Codeception\Test\Unit;
use Foodsharing\Modules\Store\PickupGateway;
use Tests\Support\UnitTester;

/**
 * Pins the opening moment of regular pickup slots: a slot becomes visible exactly
 * when it enters the store's prefetch window, in summer as well as in winter time.
 * Regression guard for #1171, where slots opened an hour late during CEST because
 * the window was computed with a fixed +01:00 offset.
 */
class PickupSlotWindowTest extends Unit
{
    private const PREFETCH_SECONDS = 3 * 24 * 3600;

    protected UnitTester $tester;
    private PickupGateway $gateway;
    private int $regionId;

    public function _before(): void
    {
        $this->gateway = $this->tester->get(PickupGateway::class);
        $this->regionId = $this->tester->createRegion()['id'];
    }

    public function _after(): void
    {
        Carbon::setTestNow();
    }

    public function testSlotOpensOnTimeDuringSummerTime(): void
    {
        // mid-July noon: deep inside CEST, where #1171 reported the one-hour delay
        $this->assertWindowBoundaryAt(Carbon::create(Carbon::now()->year + 1, 7, 15, 12, 0, 0, 'Europe/Berlin'));
    }

    public function testSlotOpensOnTimeDuringWinterTime(): void
    {
        $this->assertWindowBoundaryAt(Carbon::create(Carbon::now()->year + 1, 1, 15, 12, 0, 0, 'Europe/Berlin'));
    }

    private function assertWindowBoundaryAt(Carbon $now): void
    {
        Carbon::setTestNow($now);

        // one occurrence 30 minutes inside the prefetch window, one 30 minutes outside -
        // a one-hour offset in the window math would flip either assertion
        $inner = $now->copy()->addSeconds(self::PREFETCH_SECONDS)->subMinutes(30)->seconds(0);
        $outer = $now->copy()->addSeconds(self::PREFETCH_SECONDS)->addMinutes(30)->seconds(0);

        $this->assertSame(
            [true, false],
            [$this->slotIsListed($inner), $this->slotIsListed($outer)],
            'slot 30min inside the window must be listed, 30min outside must not (now: ' . $now->toIso8601String() . ')'
        );
    }

    private function slotIsListed(Carbon $occurrence): bool
    {
        $store = $this->tester->createStore($this->regionId, null, null, ['prefetchtime' => self::PREFETCH_SECONDS]);
        $this->tester->addRecurringPickup($store['id'], [
            'time' => $occurrence->format('H:i:s'),
            'dow' => (int)$occurrence->format('w'),
            'fetcher' => 2,
        ]);

        $slots = $this->gateway->getPickupSlots($store['id']);

        return !empty(array_filter($slots, fn ($slot) => $slot['date']->eq($occurrence)));
    }
}
