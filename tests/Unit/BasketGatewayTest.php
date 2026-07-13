<?php

declare(strict_types=1);

namespace Tests\Unit;

use Codeception\Test\Unit;
use Foodsharing\Modules\Basket\BasketGateway;
use Foodsharing\Modules\Core\DBConstants\BasketRequests\Status as RequestStatus;
use Foodsharing\Modules\Core\DTO\GeoLocation;
use Tests\Support\UnitTester;

class BasketGatewayTest extends Unit
{
    /**
     * @var UnitTester
     */
    protected $tester;

    /**
     * @var BasketGateway|null
     */
    private $gateway;
    private $foodsaver;
    private $otherFoodsaver;
    /**
     * @var array|null
     */
    private $basketIds;

    public function _before()
    {
        $this->gateway = $this->tester->get(BasketGateway::class);
        $this->foodsaver = $this->tester->createFoodsaver();
        $this->otherFoodsaver = $this->tester->createFoodsaver();
        $this->basketIds = [];

        foreach (range(1, 10) as $num) {
            $basketId = $this->tester->haveInDatabase('fs_basket', [
                'foodsaver_id' => $this->foodsaver['id'],
                'description' => 'description',
            ]);
            $this->tester->haveInDatabase('fs_basket_anfrage', [
                'basket_id' => $basketId,
                'foodsaver_id' => $this->foodsaver['id'],
                'status' => 0
            ]);
            $this->basketIds[] = $basketId;
        }

        foreach (range(1, 3) as $num) {
            $this->tester->haveInDatabase('fs_basket', [
                'foodsaver_id' => $this->foodsaver['id'],
                'description' => 'description',
                'status' => 1,
                'until' => date('Y-m-d', time() + 86400),
                'lat' => 52.520007, 'lon' => 13.404954 // Berlin
            ]);
        }

        foreach (range(1, 3) as $num) {
            $this->tester->haveInDatabase('fs_basket', [
                'foodsaver_id' => $this->foodsaver['id'],
                'description' => 'description',
                'status' => 1,
                'until' => date('Y-m-d', time() + 86400),
                'lat' => 24.453884, 'lon' => 54.377344 // miles away from Berlin
            ]);
        }
    }

    public function testGetUpdateCount(): void
    {
        $this->assertEquals(10, $this->gateway->getUpdateCount($this->foodsaver['id']));
    }

    public function testGetBasket(): void
    {
        //existing basket
        $result = $this->gateway->getBasket($this->basketIds[0]);
        $this->assertNotNull($result);

        //non-existing basket
        $this->assertNull($this->gateway->getBasket(99999));
    }

    public function testListNewestBaskets(): void
    {
        $this->assertCount(6, $this->gateway->listNewestBaskets());
    }

    public function testListNearbyBasketsByDistance(): void
    {
        $this->assertCount(
            3,
            $this->gateway->listNearbyBasketsByDistance(
                $this->otherFoodsaver['id'],
                new GeoLocation(52.520007, 13.404954), // Berlin
                50
            )
        );
    }

    public function testSetBasketStatus(): void
    {
        $this->gateway->setStatus($this->basketIds[0], RequestStatus::REQUESTED, $this->otherFoodsaver['id']);
        $request = $this->gateway->getRequestStatus($this->basketIds[0], $this->otherFoodsaver['id'], $this->foodsaver['id']);
        $this->assertEquals(RequestStatus::REQUESTED, $request['status']);
    }
}
