<?php

declare(strict_types=1);

namespace Tests\Unit;

use Codeception\Test\Unit;
use Foodsharing\Utility\TimeHelper;
use Tests\Support\UnitTester;

class TimeHelperTest extends Unit
{
    protected UnitTester $tester;
    private ?TimeHelper $timeHelper = null;

    final public function _before(): void
    {
        $this->timeHelper = $this->tester->get(TimeHelper::class);
    }

    final public function testNiceDate(): void
    {
        $testToday = $this->timeHelper->niceDate(time(), true);
        $this->assertStringStartsWith('heute', $testToday);
        $testTomorrow = $this->timeHelper->niceDate(time() + 60 * 60 * 24, true);
        $this->assertStringStartsNotWith('heute', $testTomorrow);
        $testTomorrow = $this->timeHelper->niceDate(time() + 60 * 60 * 24, false);
        $this->assertStringStartsWith('morgen', $testTomorrow);
    }
}
