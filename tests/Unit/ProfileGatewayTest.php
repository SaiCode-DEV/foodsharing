<?php

declare(strict_types=1);

namespace Tests\Unit;

use Carbon\Carbon;
use Codeception\Test\Unit;
use Foodsharing\Modules\Categories\StoreCategoryType;
use Foodsharing\Modules\Core\DBConstants\Store\StoreLogAction;
use Foodsharing\Modules\Profile\ProfileGateway;
use Tests\Support\UnitTester;

class ProfileGatewayTest extends Unit
{
    protected UnitTester $tester;
    private ProfileGateway $profileGateway;
    private $foodsaver;
    private $store;
    private $region;

    final public function _before(): void
    {
        $this->profileGateway = $this->tester->get(ProfileGateway::class);
        $this->foodsaver = $this->tester->createFoodsaver();
        $this->region = $this->tester->createRegion(fillMailbox: false);
        $this->store = $this->tester->createStore($this->region['id']);
        $pickupDate = Carbon::now();
        $pickupDate->hours(14)->minutes(45)->seconds(0);

        $date_act = Carbon::now();
        $date_act->hours(10)->minutes(45)->seconds(0);

        $this->tester->addPicker($this->store['id'], $this->foodsaver['id'], ['date' => $pickupDate]);
        $this->tester->addStoreLog($this->store['id'], $this->foodsaver['id'], $this->foodsaver['id'], StoreLogAction::SIGN_UP_SLOT, ['date_reference' => $pickupDate, 'date_activity' => $date_act]);
    }

    final public function testGetSecuredPickupsCount(): void
    {
        $count = $this->profileGateway->getSecuredPickupsCount($this->foodsaver['id'], 0);
        $this->assertEquals(1, $count);
    }

    final public function testListStoresOfFoodsaverIncludesCategoryType(): void
    {
        $this->tester->createStoreCategories();

        $categoryId = 16; // corresponds to a GIVING category in the seeded categories
        $store = $this->tester->createStore($this->region['id'], null, null, ['betrieb_kategorie_id' => $categoryId]);
        $this->tester->addStoreTeam($store['id'], $this->foodsaver['id'], false, false, true);

        $stores = $this->profileGateway->listStoresOfFoodsaver($this->foodsaver['id']);

        $found = null;
        foreach ($stores as $s) {
            if ($s['id'] === $store['id']) {
                $found = $s;
                break;
            }
        }

        $this->assertNotNull($found, 'Created store not found in listStoresOfFoodsaver result');
        $this->assertEquals(StoreCategoryType::GIVING->value, (int)$found['categoryType']);
    }
}
