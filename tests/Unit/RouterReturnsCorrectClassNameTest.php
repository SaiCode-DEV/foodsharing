<?php

declare(strict_types=1);

namespace Tests\Unit;

use Codeception\Test\Unit;
use Foodsharing\Lib\Routing;
use Foodsharing\Modules\Index\IndexControl;
use Tests\Support\UnitTester;

class RouterReturnsCorrectClassNameTest extends Unit
{
    protected UnitTester $tester;

    // tests
    final public function testReturnNullOnInvalidAppName(): void
    {
        $this->assertNull(Routing::getClassName('IAmaSurelyNotExistingApp'));
    }

    final public function testReturnFqcnForControlClass(): void
    {
        $actual = Routing::getClassName('index');
        $this->assertEquals(IndexControl::class, $actual);
    }
}
