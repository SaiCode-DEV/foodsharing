<?php

declare(strict_types=1);

namespace Tests\Unit;

use Codeception\Test\Unit;
use Faker\Factory;
use Faker\Generator;
use Foodsharing\Modules\Configuration\ConfigurationGateway;
use Foodsharing\Modules\Core\DatabaseNoValueFoundException;
use Tests\Support\UnitTester;

class ConfigurationGatewayTest extends Unit
{
    protected UnitTester $tester;
    private ConfigurationGateway $gateway;
    private Generator $faker;

    public function _before()
    {
        $this->gateway = $this->tester->get(ConfigurationGateway::class);
        $this->faker = Factory::create('de_DE');
    }

    public function testGetNonexistentKey(): void
    {
        $key = $this->faker->unique()->word();
        $this->expectException(DatabaseNoValueFoundException::class);
        $this->gateway->getEntry($key);
    }

    public function testAddKey(): void
    {
        // without category
        $key = $this->faker->unique()->word();
        $value = $this->faker->text(1000);

        $this->gateway->addOrUpdateEntry($key, $value);
        $this->assertEquals($value, $this->gateway->getEntry($key));
        $this->assertEquals(null, $this->gateway->getCategory($key));

        // with new category
        $key2 = $this->faker->unique()->word();
        $value2 = $this->faker->text(1000);
        $category2 = $this->faker->numberBetween(1, 10);

        $this->gateway->addOrUpdateEntry($key2, $value2, $category2);
        $this->assertEquals($value2, $this->gateway->getEntry($key2));
        $this->assertEquals($category2, $this->gateway->getCategory($key2));
    }

    public function testReplaceValue(): void
    {
        $key = $this->faker->unique()->word();
        $value = $this->faker->text(1000);
        $category = $this->faker->numberBetween(1, 10);
        $this->gateway->addOrUpdateEntry($key, $value, $category);

        // replace value
        $value2 = $this->faker->text(1000);
        $this->gateway->addOrUpdateEntry($key, $value2);
        $this->assertEquals($value2, $this->gateway->getEntry($key));
        $this->assertEquals($category, $this->gateway->getCategory($key));

        // replace value and category
        $value3 = $this->faker->text(1000);
        $category3 = $this->faker->numberBetween(11, 20);
        $this->gateway->addOrUpdateEntry($key, $value3, $category3);
        $this->assertEquals($value3, $this->gateway->getEntry($key));
        $this->assertEquals($category3, $this->gateway->getCategory($key));
    }

    public function testAddKeys(): void
    {
        // Without category
        $entries = $this->randomKeyValuePairs($this->faker->numberBetween(1, 10));
        $this->gateway->addOrUpdateEntries($entries);
        foreach ($entries as $key => $value) {
            $this->assertEquals($value, $this->gateway->getEntry($key));
            $this->assertEquals(null, $this->gateway->getCategory($key));
        }

        // With category
        $entries2 = $this->randomKeyValuePairs($this->faker->numberBetween(1, 10));
        $category2 = $this->faker->numberBetween(1, 10);
        $this->gateway->addOrUpdateEntries($entries2, $category2);
        foreach ($entries2 as $key => $value) {
            $this->assertEquals($value, $this->gateway->getEntry($key));
            $this->assertEquals($category2, $this->gateway->getCategory($key));
        }
    }

    public function testSetCategoryAndGetEntries(): void
    {
        $category1 = $this->randomKeyValuePairs($this->faker->numberBetween(1, 10));
        $this->gateway->addOrUpdateEntries($category1);
        $this->gateway->setCategory(1, ...array_keys($category1));

        $category2 = $this->randomKeyValuePairs($this->faker->numberBetween(1, 10));
        $this->gateway->addOrUpdateEntries($category2);
        $this->gateway->setCategory(2, ...array_keys($category2));

        $withoutCategory = $this->randomKeyValuePairs($this->faker->numberBetween(1, 10));
        $this->gateway->addOrUpdateEntries($withoutCategory);

        $this->assertEquals($category1, $this->gateway->getEntries(1));
        $this->assertEquals($category2, $this->gateway->getEntries(2));
        $this->assertEquals($withoutCategory, $this->gateway->getEntries(null));
    }

    public function testGetNonExistentEntries(): void
    {
        $category1 = $this->randomKeyValuePairs($this->faker->numberBetween(1, 10));
        $this->gateway->addOrUpdateEntries($category1, 1);

        $this->assertEquals([], $this->gateway->getEntries(2));
    }

    public function testGetCategoryOfNonexistentKey(): void
    {
        $key = $this->faker->unique()->word();
        $this->expectException(DatabaseNoValueFoundException::class);
        $this->gateway->getCategory($key);
    }

    public function testSetCategory(): void
    {
        $key = $this->faker->unique()->word();
        $value = $this->faker->text(1000);
        $category1 = $this->faker->numberBetween(0, 100);
        $category2 = $this->faker->numberBetween(0, 100);

        $this->gateway->addOrUpdateEntry($key, $value);
        $this->assertEquals(null, $this->gateway->getCategory($key));
        $this->gateway->setCategory($category1, $key);
        $this->assertEquals($category1, $this->gateway->getCategory($key));
        $this->gateway->setCategory($category2, $key);
        $this->assertEquals($category2, $this->gateway->getCategory($key));
    }

    public function testSetCategoryOfNonexistentKey(): void
    {
        $key = $this->faker->unique()->word();
        $category = $this->faker->numberBetween(0, 100);
        $this->expectException(DatabaseNoValueFoundException::class);
        $this->gateway->setCategory($category, $key);
    }

    private function randomKeyValuePairs(int $number): array
    {
        $pairs = [];
        for ($i = 0; $i < $number; ++$i) {
            $pairs[$this->faker->unique()->word()] = $this->faker->text(1000);
        }

        return $pairs;
    }
}
