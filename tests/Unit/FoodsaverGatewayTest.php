<?php

declare(strict_types=1);

namespace Tests\Unit;

use Codeception\Test\Unit;
use Foodsharing\Modules\Foodsaver\FoodsaverGateway;
use Tests\Support\UnitTester;

class FoodsaverGatewayTest extends Unit
{
    protected UnitTester $tester;
    private FoodsaverGateway $gateway;
    private $foodsharer;
    private $foodsaver;
    private $region;
    private $regionMember;
    private $regionAdmin;

    final public function _before(): void
    {
        $this->gateway = $this->tester->get(FoodsaverGateway::class);

        $this->foodsharer = $this->tester->createFoodsharer(null, ['newsletter' => 1]);
        $this->foodsaver = $this->tester->createFoodsaver(null, ['newsletter' => 1]);

        $this->region = $this->tester->createRegion('TestRegion', fillMailbox: false);
        $regionId = $this->region['id'];
        $this->regionMember = $this->tester->createFoodsaver(null, ['bezirk_id' => $regionId, 'newsletter' => 0]);
        $this->regionAdmin = $this->tester->createAmbassador(null, ['bezirk_id' => $regionId, 'newsletter' => 0]);
        $this->tester->addRegionAdmin($regionId, $this->regionAdmin['id']);
    }

    final public function testUpdateProfile(): void
    {
        $this->gateway->updateProfile($this->foodsaver['id'], ['anschrift' => 'my new street']);
        $this->tester->seeInDatabase('fs_foodsaver', [
            'id' => $this->foodsaver['id'],
            'anschrift' => 'my new street'
        ]);

        // strips tags
        $this->gateway->updateProfile($this->foodsaver['id'], ['anschrift' => '<script>my new street']);
        $this->tester->seeInDatabase('fs_foodsaver', [
            'id' => $this->foodsaver['id'],
            'anschrift' => 'my new street'
        ]);
    }

    final public function testGetPhoto(): void
    {
        $this->gateway->updatePhoto($this->foodsaver['id'], 'mypicture.png');
        $this->tester->assertEquals(
            $this->gateway->getPhotoFileName($this->foodsaver['id']),
            'mypicture.png'
        );
    }

    final public function testUpdateGroupMembersByAdditionWhileLeavingAdmin(): void
    {
        $regionId = $this->region['id'];
        $newFoodsaver = $this->tester->createFoodsaver();

        $fsIds = [$newFoodsaver['id'], $this->regionMember['id']];
        $updates = $this->gateway->updateGroupMembers($regionId, $fsIds, true);

        $this->tester->seeInDatabase('fs_foodsaver_has_bezirk', ['foodsaver_id' => $newFoodsaver['id'], 'bezirk_id' => $regionId]);
        $this->tester->seeInDatabase('fs_foodsaver_has_bezirk', ['foodsaver_id' => $this->regionAdmin['id'], 'bezirk_id' => $regionId]);
        $this->tester->assertEquals(1, $updates['inserts'], 'Wrong number of inserts for!');
        $this->tester->assertEquals(0, $updates['deletions'], 'Wrong number of deletions!');
    }

    final public function testUpdateGroupMembersByAdditionWhileNotLeavingAdmin(): void
    {
        $regionId = $this->region['id'];
        $newFoodsaver = $this->tester->createFoodsaver();

        $fsIds = [$newFoodsaver['id'], $this->regionMember['id']];
        $updates = $this->gateway->updateGroupMembers($regionId, $fsIds, false);

        $this->tester->seeInDatabase('fs_foodsaver_has_bezirk', ['foodsaver_id' => $newFoodsaver['id'], 'bezirk_id' => $regionId]);
        $this->tester->dontSeeInDatabase('fs_foodsaver_has_bezirk', ['foodsaver_id' => $this->regionAdmin['id'], 'bezirk_id' => $regionId]);
        $this->tester->assertEquals(1, $updates['inserts'], 'Wrong number of inserts!');
        $this->tester->assertEquals(1, $updates['deletions'], 'Wrong number of deletions!');
    }

    final public function testUpdateGroupMembersByDeletionWhileLeavingAdmin(): void
    {
        $regionId = $this->region['id'];

        $updates = $this->gateway->updateGroupMembers($regionId, [], true);

        $this->tester->dontSeeInDatabase('fs_foodsaver_has_bezirk', ['foodsaver_id' => $this->regionMember['id'], 'bezirk_id' => $regionId]);
        $this->tester->seeInDatabase('fs_foodsaver_has_bezirk', ['foodsaver_id' => $this->regionAdmin['id'], 'bezirk_id' => $regionId]);
        $this->tester->assertEquals(0, $updates['inserts'], 'Wrong number of inserts!');
        $this->tester->assertEquals(1, $updates['deletions'], 'Wrong number of deletions!');
    }

    final public function testUpdateGroupMembersByDeletionWhileNotLeavingAdmin(): void
    {
        $regionId = $this->region['id'];

        $updates = $this->gateway->updateGroupMembers($regionId, [], false);

        $this->tester->dontSeeInDatabase('fs_foodsaver_has_bezirk', ['foodsaver_id' => $this->regionMember['id'], 'bezirk_id' => $regionId]);
        $this->tester->dontSeeInDatabase('fs_foodsaver_has_bezirk', ['foodsaver_id' => $this->regionAdmin['id'], 'bezirk_id' => $regionId]);
        $this->tester->assertEquals(0, $updates['inserts'], 'Wrong number of inserts!');
        $this->tester->assertEquals(2, $updates['deletions'], 'Wrong number of deletions!');
    }

    final public function testGetRegionFoodsaversEmailList(): void
    {
        $regions = [0 => $this->region['id'], 1 => 0];

        $emails = $this->gateway->getEmailAddressesFromRegions($regions);

        $this->tester->assertIsArray($emails);
        $this->tester->assertCount(2, $emails);
        $bot = $this->regionAdmin;
        $this->tester->assertArrayHasKey($bot['id'], $emails);
        $this->tester->assertEquals($bot['email'], $emails[$bot['id']]['email']);
        $fs = $this->regionMember;
        $this->tester->assertEquals($fs['email'], $emails[$fs['id']]['email']);
    }

    final public function testGetSingleEmailAddress(): void
    {
        $email = $this->gateway->getEmailAddress($this->foodsaver['id']);

        $this->tester->assertEquals($this->foodsaver['email'], $email);
    }

    final public function testGetAllEmailAddressesFromNewsletterSubscribers(): void
    {
        $foodsavers = [$this->foodsharer, $this->foodsaver];
        $expectedResult = array_map(function ($fs) {
            return [
                'id' => $fs['id'],
                'firstName' => $fs['name'],
                'email' => $fs['email']
            ];
        }, $foodsavers);

        $emails = $this->gateway->getNewsletterSubscribers();

        $this->tester->assertCount(count($expectedResult), $emails);
        $result = array_map(fn ($o) => get_object_vars($o), $emails); //$this->serializeEmails($emails);
        codecept_debug($expectedResult);
        codecept_debug($result);
        $this->tester->assertEquals($expectedResult, $result);
    }

    final public function testGetActiveAmbassadors(): void
    {
        $inactiveAmbassador = $this->tester->createAmbassador(null, ['active' => 0]);

        $foodsavers = $this->gateway->getActiveAmbassadors();

        $this->tester->assertCount(1, $foodsavers);
        $this->tester->assertEquals($this->regionAdmin['id'], $foodsavers[0]['id']);
    }

    final public function testGetFoodsaversWithoutAmbassadors(): void
    {
        $foodsavers = $this->gateway->getFoodsaversWithoutAmbassadors();

        $this->tester->assertCount(3, $foodsavers);
    }

    final public function testFoodsaverExists(): void
    {
        $randomNotExistingFsId = 1_238_513_513;
        $this->tester->assertFalse($this->gateway->foodsaverExists($randomNotExistingFsId));
        $fs = $this->tester->createFoodsaver();
        $this->tester->assertTrue($this->gateway->foodsaverExists($fs['id']));
        $this->gateway->deleteFoodsaver($fs['id'], null, null);
        $this->tester->assertFalse($this->gateway->foodsaverExists($fs['id']));
    }

    final public function testFoodsaversExist(): void
    {
        $randomNotExistingFsId = 1_238_513_513;
        $fs = $this->tester->createFoodsaver();
        $fs2 = $this->tester->createFoodsaver();
        $this->tester->assertFalse($this->gateway->foodsaversExist([$randomNotExistingFsId, $fs['id']]));
        $this->tester->assertTrue($this->gateway->foodsaversExist([$fs['id']]));
        $this->tester->assertTrue($this->gateway->foodsaversExist([$fs['id'], $fs2['id']]));
    }
}
