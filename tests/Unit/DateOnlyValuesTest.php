<?php

declare(strict_types=1);

namespace Tests\Unit;

use Carbon\Carbon;
use Codeception\Test\Unit;
use DateTime;
use DateTimeZone;
use Tests\Support\UnitTester;

/**
 * Pins how a stored date without a time is turned into a date object: the day has
 * to survive, whatever time it is right now. Regression guard for #2813, where
 * `createFromFormat('Y-m-d', ...)` filled in the current time, so a date read late
 * in the evening serialized as the previous day in UTC.
 */
class DateOnlyValuesTest extends Unit
{
    protected UnitTester $tester;

    public function _after(): void
    {
        Carbon::setTestNow();
    }

    /**
     * Late evening in German summer time, which is the window where the day flips
     * when the current time leaks into a date-only value.
     */
    private const CRITICAL_MOMENT = '2026-08-23 23:30:00';

    public function testCarbonKeepsTheStoredDayLateInTheEvening(): void
    {
        Carbon::setTestNow(Carbon::parse(self::CRITICAL_MOMENT, 'Europe/Berlin'));

        $date = Carbon::createFromFormat('!Y-m-d', '2022-09-01', new DateTimeZone('UTC'));

        $this->assertSame('2022-09-01', $date->format('Y-m-d'));
        $this->assertStringStartsWith('2022-09-01', $date->toISOString());
    }

    public function testDateTimeKeepsTheStoredDayLateInTheEvening(): void
    {
        Carbon::setTestNow(Carbon::parse(self::CRITICAL_MOMENT, 'Europe/Berlin'));

        $date = DateTime::createFromFormat('!Y-m-d', '2022-09-01', new DateTimeZone('UTC'));

        $this->assertSame('2022-09-01 00:00:00', $date->format('Y-m-d H:i:s'));
    }

    /**
     * The two ways of getting it wrong, kept as counter examples: without the `!`
     * the time parts stay at "now", and with local midnight the day still moves
     * once it is serialized as UTC.
     */
    public function testFormatWithoutResetPicksUpTheCurrentTime(): void
    {
        Carbon::setTestNow(Carbon::parse(self::CRITICAL_MOMENT, 'Europe/Berlin'));

        $date = Carbon::createFromFormat('Y-m-d', '2022-09-01');

        $this->assertSame('23:30:00', $date->format('H:i:s'));
    }

    public function testLocalMidnightStillShiftsTheDayInUtc(): void
    {
        $date = Carbon::createFromFormat('!Y-m-d', '2022-09-01', new DateTimeZone('Europe/Berlin'));

        $this->assertStringStartsWith('2022-08-31', $date->toISOString());
    }
}
