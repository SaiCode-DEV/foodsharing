<?php

declare(strict_types=1);

namespace Tests\Unit;

use Codeception\Test\Unit;
use Faker\Factory;
use Faker\Generator;
use Foodsharing\Modules\Bell\BellGateway;
use Foodsharing\Modules\Bell\DTO\Bell;
use Foodsharing\Modules\Bell\DTO\BellForList;
use Tests\Support\UnitTester;

class BellGatewayTest extends Unit
{
    protected UnitTester $tester;
    private BellGateway $gateway;
    private Generator $faker;

    public function _before()
    {
        $this->gateway = $this->tester->get(BellGateway::class);
        $this->faker = Factory::create('de_DE');
    }

    public function testAddBell(): void
    {
        $user1 = $this->tester->createFoodsaver();
        $user2 = $this->tester->createFoodsaver();
        $bellData = Bell::create(
            'first bell title',
            $this->faker->text(50),
            '',
            [''],
            [],
            '',
            true,
        );
        /* addBell accepts different inputs: $id, [$id, $id], [['id' => $id]] */
        $this->gateway->addBell([$user1, $user2], $bellData);
        $bellId = $this->tester->grabFromDatabase('fs_bell', 'id', ['name' => $bellData->title, 'body' => $bellData->body]);
        $this->tester->seeInDatabase('fs_foodsaver_has_bell', ['foodsaver_id' => $user1['id'], 'bell_id' => $bellId, 'seen' => 0]);
        $this->tester->seeInDatabase('fs_foodsaver_has_bell', ['foodsaver_id' => $user2['id'], 'bell_id' => $bellId, 'seen' => 0]);

        $bellData->title = 'second bell title';
        $this->gateway->addBell([$user1, $user2], $bellData);
        $bellId = $this->tester->grabFromDatabase('fs_bell', 'id', ['name' => $bellData->title, 'body' => $bellData->body]);
        $this->tester->seeInDatabase('fs_foodsaver_has_bell', ['foodsaver_id' => $user1['id'], 'bell_id' => $bellId, 'seen' => 0]);
        $this->tester->seeInDatabase('fs_foodsaver_has_bell', ['foodsaver_id' => $user2['id'], 'bell_id' => $bellId, 'seen' => 0]);
    }

    public function testAddBellWithSameUserMultiplyTimes()
    {
        $user1 = $this->tester->createFoodsaver();
        $user2 = $this->tester->createFoodsaver();
        $bellData = Bell::create(
            'bell should only exist once for each user',
            $this->faker->text(50),
            '',
            [''],
            [],
            '',
            true,
        );

        $this->gateway->addBellForUsers([$user1['id'], $user1['id'], $user2['id']], $bellData);
        $bellId = $this->tester->grabFromDatabase('fs_bell', 'id', ['name' => $bellData->title, 'body' => $bellData->body]);
        $this->tester->seeInDatabase('fs_foodsaver_has_bell', ['foodsaver_id' => $user1['id'], 'bell_id' => $bellId, 'seen' => 0]);
        $this->tester->seeInDatabase('fs_foodsaver_has_bell', ['foodsaver_id' => $user2['id'], 'bell_id' => $bellId, 'seen' => 0]);
    }

    public function testRemoveBellWorksIfIdentifierIsCorrect(): void
    {
        $this->tester->clearTable('fs_bell');
        $this->tester->clearTable('fs_foodsaver_has_bell');

        $user1 = $this->tester->createFoodsaver();
        $user2 = $this->tester->createFoodsaver();

        $this->tester->addBells([$user1, $user2], ['identifier' => 'my-custom-identifier']);
        $this->tester->seeNumRecords(1, 'fs_bell');
        $this->tester->seeNumRecords(2, 'fs_foodsaver_has_bell');

        $this->gateway->delBellsByIdentifier('my-custom-identifier');

        $this->tester->seeNumRecords(0, 'fs_bell');
        $this->tester->seeNumRecords(0, 'fs_foodsaver_has_bell');
    }

    public function testRemoveBellDoesNotWorkIfIdentifierIsIncorrect(): void
    {
        $this->tester->clearTable('fs_bell');
        $this->tester->clearTable('fs_foodsaver_has_bell');

        $user1 = $this->tester->createFoodsaver();
        $user2 = $this->tester->createFoodsaver();

        $this->tester->addBells([$user1, $user2], ['identifier' => 'my-custom-identifier']);
        $this->tester->seeNumRecords(1, 'fs_bell');
        $this->tester->seeNumRecords(2, 'fs_foodsaver_has_bell');

        $this->gateway->delBellsByIdentifier('my-custom-wrong-identifier');

        $this->tester->seeNumRecords(1, 'fs_bell');
        $this->tester->seeNumRecords(2, 'fs_foodsaver_has_bell');
    }

    public function testGetOneByIdentifier(): void
    {
        $this->tester->clearTable('fs_bell');

        $user1 = $this->tester->createFoodsaver();
        $user2 = $this->tester->createFoodsaver();

        $identifier = 'my-custom-identifier';

        $this->tester->addBells([$user1, $user2], ['identifier' => $identifier]);

        $bellId = $this->gateway->getOneByIdentifier($identifier);

        $this->tester->seeInDatabase('fs_bell', ['id' => $bellId, 'identifier' => $identifier]);
    }

    /** Helper to find specific bell in array of bells */
    private function findBellInArrayById(array $array, int $id): ?BellForList
    {
        $result = array_filter($array, fn ($obj) => $obj->id == $id);

        return reset($result) ?: false;
    }

    public function testStorageMappingForBellIconOrImage(): void
    {
        $this->tester->clearTable('fs_bell');
        $user1 = $this->tester->createFoodsaver();
        $identifier = 'my-custom-identifier';

        // Create bell objects in database for testing
        $bellIdWithNoIconOrImage = $this->tester->addBells([$user1], ['identifier' => $identifier, 'icon' => null]);
        $bellIdWithEmptyPath = $this->tester->addBells([$user1], ['identifier' => $identifier, 'icon' => '']);
        $bellIdForIcon = $this->tester->addBells([$user1], ['identifier' => $identifier, 'icon' => 'css-icon-class-name']);
        $bellIdForImagePath = $this->tester->addBells([$user1], ['identifier' => $identifier, 'icon' => '/image.png']);
        $bells = $this->gateway->listBells($user1['id']);

        // check expected values
        $bellWithNoIconOrImage = $this->findBellInArrayById($bells, $bellIdWithNoIconOrImage);
        $this->assertNotFalse($bellWithNoIconOrImage, 'Unable to find related bell for test');
        $this->assertEquals(null, $bellWithNoIconOrImage->image);
        $this->assertEquals(null, $bellWithNoIconOrImage->icon);

        $bellWithEmptyPath = $this->findBellInArrayById($bells, $bellIdWithEmptyPath);
        $this->assertNotFalse($bellWithEmptyPath, 'Unable to find related bell for test');
        $this->assertEquals(null, $bellWithEmptyPath->image);
        $this->assertEquals(null, $bellWithEmptyPath->icon);

        $bellForIcon = $this->findBellInArrayById($bells, $bellIdForIcon);
        $this->assertNotFalse($bellForIcon, 'Unable to find related bell for test');
        $this->assertEquals(null, $bellForIcon->image);
        $this->assertEquals('css-icon-class-name', $bellForIcon->icon);

        $bellForImagePath = $this->findBellInArrayById($bells, $bellIdForImagePath);
        $this->assertNotFalse($bellForImagePath, 'Unable to find related bell for test');
        $this->assertEquals('/image.png', $bellForImagePath->image);
        $this->assertEquals(null, $bellForImagePath->icon);
    }

    public function testUpdateBell(): void
    {
        $this->tester->clearTable('fs_bell');

        $user1 = $this->tester->createFoodsaver();
        $user2 = $this->tester->createFoodsaver();

        $bellData = Bell::create(
            'title',
            $this->faker->text(50),
            'some-icon',
            [],
            [],
            'some-identifier',
            $closable = false,
        );

        $this->gateway->addBell([$user1, $user2], $bellData);
        $bellId = $this->tester->grabFromDatabase('fs_bell', 'id', ['name' => $bellData->title, 'body' => $bellData->body]);

        $updatedData = [
            'name' => 'updated title',
            'body' => $this->faker->text(50),
            'icon' => 'some-updated-icon',
            'identifier' => 'some-updated-identifier',
            'closeable' => true,
        ];

        $this->gateway->updateBell($bellId, $updatedData);

        $this->tester->seeInDatabase('fs_bell', $updatedData);
    }

    public function testSetReadStatusOnBell(): void
    {
        $this->tester->clearTable('fs_bell');

        $user1 = $this->tester->createFoodsaver();
        $user2 = $this->tester->createFoodsaver();

        $bellData = Bell::create(
            'title',
            $this->faker->text(50),
            'some-icon',
            [],
            [],
            'some-identifier',
            $closable = false,
        );

        $this->gateway->addBell([$user1, $user2], $bellData);
        $bellId = $this->tester->grabFromDatabase('fs_bell', 'id', ['name' => $bellData->title, 'body' => $bellData->body]);

        $this->gateway->setReadStatus([$bellId], $user1['id'], 1);
        $this->gateway->setReadStatus([$bellId], $user2['id'], 0);

        $this->tester->seeInDatabase('fs_foodsaver_has_bell', ['foodsaver_id' => $user1['id'], 'bell_id' => $bellId, 'seen' => 1]);
        $this->tester->seeInDatabase('fs_foodsaver_has_bell', ['foodsaver_id' => $user2['id'], 'bell_id' => $bellId, 'seen' => 0]);
    }
}
