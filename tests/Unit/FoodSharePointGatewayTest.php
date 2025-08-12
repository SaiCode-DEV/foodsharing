<?php

declare(strict_types=1);

namespace Tests\Unit;

use Codeception\Test\Unit;
use Exception;
use Faker\Factory;
use Foodsharing\Modules\Core\DBConstants\FoodSharePoint\FollowerType;
use Foodsharing\Modules\Core\DBConstants\Info\InfoType;
use Foodsharing\Modules\Core\DTO\GeoLocation;
use Foodsharing\Modules\FoodSharePoint\FoodSharePointGateway;
use Foodsharing\RestApi\Models\FoodSharePoint\FoodSharePointEditData;
use Foodsharing\RestApi\Models\FoodSharePoint\FoodSharePointForCreation;
use Tests\Support\UnitTester;

class FoodSharePointGatewayTest extends Unit
{
    protected UnitTester $tester;
    private FoodSharePointGateway $gateway;
    private array $foodsaver;
    private array $otherFoodsaver;
    private array $foodSharePoint;
    private array $region;

    final public function _before(): void
    {
        $faker = Factory::create('de_DE');
        $this->gateway = $this->tester->get(FoodSharePointGateway::class);
        $this->foodsaver = $this->tester->createFoodsaver();
        $this->otherFoodsaver = $this->tester->createFoodsaver();
        $this->region = $this->tester->createRegion('peter', fillMailbox: false);

        $this->foodSharePoint = $this->tester->createFoodSharePoint(
            $this->foodsaver['id'],
            $this->region['id'],
            ['picture' => '/api/uploads/' . $faker->uuid()]
        );
    }

    final public function testUpdateFoodSharePoint(): void
    {
        $data = new FoodSharePointEditData();
        $data->regionId = $this->region['id'];
        $data->name = 'asdf';
        $data->description = $this->foodSharePoint['desc'];
        $data->address = $this->foodSharePoint['anschrift'];
        $data->postalCode = $this->foodSharePoint['plz'];
        $data->city = $this->foodSharePoint['ort'];
        $data->location = new GeoLocation();
        $data->location->lat = $this->foodSharePoint['lat'];
        $data->location->lon = $this->foodSharePoint['lon'];
        $data->picture = 'picture/cat.jpg';
        $id = $this->gateway->addFoodSharePoint(
            $this->foodsaver['id'],
            $data,
            true
        );

        $response = $this->gateway->updateFoodSharePoint($this->foodSharePoint['id'], $data);
        $this->assertTrue($response);
        $this->tester->seeInDatabase('fs_fairteiler', ['name' => 'asdf']);
    }

    final public function testUpdateFoodSharePointReturnsTrueIfNothingChanged(): void
    {
        $data = new FoodSharePointEditData();
        $data->regionId = $this->region['id'];
        $data->name = $this->foodSharePoint['name'];
        $data->description = $this->foodSharePoint['desc'];
        $data->address = $this->foodSharePoint['anschrift'];
        $data->postalCode = $this->foodSharePoint['plz'];
        $data->city = $this->foodSharePoint['ort'];
        $data->location = new GeoLocation();
        $data->location->lat = $this->foodSharePoint['lat'];
        $data->location->lon = $this->foodSharePoint['lon'];
        $data->picture = '';
        $id = $this->gateway->addFoodSharePoint(
            $this->foodsaver['id'],
            $data,
            true
        );
        $response = $this->gateway->updateFoodSharePoint(
            $this->foodSharePoint['id'], $data
        );
        $this->assertTrue($response);
        $this->tester->seeInDatabase('fs_fairteiler', ['name' => $this->foodSharePoint['name']]);
    }

    final public function testUpdateFoodSharePointDoesNotStripTags(): void
    {
        /* strip_tags happens in the controller in this case */
        $this->tester->dontSeeInDatabase('fs_fairteiler', ['name' => 'asdf']);

        $data = new FoodSharePointEditData();
        $data->regionId = $this->region['id'];
        $data->name = 'asdf<script>';
        $data->description = $this->foodSharePoint['desc'];
        $data->address = $this->foodSharePoint['anschrift'];
        $data->postalCode = $this->foodSharePoint['plz'];
        $data->city = $this->foodSharePoint['ort'];
        $data->location = new GeoLocation();
        $data->location->lat = $this->foodSharePoint['lat'];
        $data->location->lon = $this->foodSharePoint['lon'];
        $data->picture = '';
        $id = $this->gateway->addFoodSharePoint(
            $this->foodsaver['id'],
            $data,
            true
        );

        $response = $this->gateway->updateFoodSharePoint(
            $this->foodSharePoint['id'],
            $data
        );
        $this->assertTrue($response);
        $this->tester->seeInDatabase('fs_fairteiler', ['name' => 'asdf<script>']);
        $this->tester->dontSeeInDatabase('fs_fairteiler', ['name' => 'asdf']);
    }

    final public function testUpdateFoodSharePointThrowsIfIDNotFound(): void
    {
        $this->expectException(Exception::class);
        $data = new FoodSharePointEditData();
        $this->gateway->updateFoodSharePoint(
            99_999_999, $data
        );
        $this->tester->dontSeeInDatabase('fs_fairteiler', ['name' => 'asdf']);
    }

    final public function testFollow(): void
    {
        $params = [
            'fairteiler_id' => $this->foodSharePoint['id'],
            'foodsaver_id' => $this->otherFoodsaver['id'],
            'infotype' => InfoType::EMAIL,
            'type' => FollowerType::FOLLOWER
        ];
        $this->tester->dontSeeInDatabase('fs_fairteiler_follower', $params);
        $this->gateway->follow($this->otherFoodsaver['id'], $this->foodSharePoint['id'], InfoType::EMAIL);
        $this->tester->seeInDatabase('fs_fairteiler_follower', $params);
    }

    final public function testFollowDoesNotOverwriteExistingType(): void
    {
        $params = [
            'fairteiler_id' => $this->foodSharePoint['id'],
            'foodsaver_id' => $this->foodsaver['id'],
            'infotype' => InfoType::EMAIL,
            'type' => FollowerType::FOOD_SHARE_POINT_MANAGER
        ];

        // Our foodsaver is an admin of the food share point so already has a type 2 entry
        $this->tester->seeInDatabase('fs_fairteiler_follower', $params);

        $this->gateway->follow($this->foodsaver['id'], $this->foodSharePoint['id'], InfoType::EMAIL);

        // They should keep their type 2 (meaning admin)
        $this->tester->seeInDatabase('fs_fairteiler_follower', $params);

        // And should not have a type 1 entry
        $this->tester->dontSeeInDatabase('fs_fairteiler_follower', array_merge($params, ['type' => 1]));
    }

    final public function testUnfollow(): void
    {
        // Our foodsaver is an admin of the food share point so has a type 2 entry
        $this->tester->seeInDatabase('fs_fairteiler_follower', [
            'fairteiler_id' => $this->foodSharePoint['id'],
            'foodsaver_id' => $this->foodsaver['id'],
            'infotype' => InfoType::EMAIL,
            'type' => FollowerType::FOOD_SHARE_POINT_MANAGER
        ]);

        $this->gateway->unfollow($this->foodsaver['id'], $this->foodSharePoint['id']);

        // There are now no follow entries for this food share point/foodsaver combination
        $this->tester->dontSeeInDatabase('fs_fairteiler_follower', [
            'fairteiler_id' => $this->foodSharePoint['id'],
            'foodsaver_id' => $this->foodsaver['id'],
        ]);
    }

    final public function testGetFoodSharePoint(): void
    {
        $foodSharePoint = $this->gateway->getFoodSharePoint($this->foodSharePoint['id']);
        $this->assertEquals($foodSharePoint->id, $this->foodSharePoint['id']);
        $this->assertEquals($foodSharePoint->picture, $this->foodSharePoint['picture']);
    }

    final public function testAddFoodSharePoint(): void
    {
        $data = new FoodSharePointForCreation();
        $data->regionId = $this->region['id'];
        $data->name = 'my nice new food share point';
        $data->description = $this->foodSharePoint['desc'];
        $data->address = $this->foodSharePoint['anschrift'];
        $data->postalCode = $this->foodSharePoint['plz'];
        $data->city = $this->foodSharePoint['ort'];
        $data->location = new GeoLocation();
        $data->location->lat = $this->foodSharePoint['lat'];
        $data->location->lon = $this->foodSharePoint['lon'];
        $data->picture = 'picture/cat.jpg';
        $id = $this->gateway->addFoodSharePoint(
            $this->foodsaver['id'],
            $data,
            true
        );

        $this->assertGreaterThanOrEqual(0, $id);

        $this->tester->seeInDatabase('fs_fairteiler', [
            'name' => 'my nice new food share point'
        ]);

        $this->tester->seeInDatabase('fs_fairteiler_follower', [
            'fairteiler_id' => $id,
            'foodsaver_id' => $this->foodsaver['id'],
        ]);
    }
}
