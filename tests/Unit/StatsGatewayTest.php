<?php

declare(strict_types=1);

namespace Tests\Unit;

use Carbon\Carbon;
use Codeception\Test\Unit;
use Faker\Factory;
use Foodsharing\Modules\Core\DBConstants\Configuration\ConfigurationKey;
use Foodsharing\Modules\Stats\StatsGateway;
use Tests\Support\UnitTester;

class StatsGatewayTest extends Unit
{
    protected UnitTester $tester;
    private StatsGateway $gateway;

    private array $foodsaver;
    private array $otherFoodsaver;
    private array $thirdFoodsaver;
    private array $fourthFoodsaver;
    private array $region;

    public function _before(): void
    {
        $faker = Factory::create('de_DE');
        $this->gateway = $this->tester->get(StatsGateway::class);

        $this->region = $this->tester->createRegion(fillMailbox: false);
        $this->foodsaver = $this->tester->createFoodsaver(extra_params: [
            'bezirk_id' => $this->region['id'],
            'stat_fetchcount' => $faker->numberBetween(0, 1000),
            'stat_fetchweight' => $faker->numberBetween(0, 1000),
            'stat_givecount' => $faker->numberBetween(0, 1000),
            'stat_engagecount' => $faker->numberBetween(0, 1000),
            'stat_postcount' => $faker->numberBetween(0, 1000),
            'stat_bananacount' => $faker->numberBetween(0, 1000),
            'stat_buddycount' => $faker->numberBetween(0, 1000),
        ]);
        $this->otherFoodsaver = $this->tester->createFoodsaver(extra_params: [
            'bezirk_id' => $this->region['id'],
        ]);
        $this->thirdFoodsaver = $this->tester->createFoodsaver(extra_params: [
            'bezirk_id' => $this->region['id'],
        ]);
        $this->fourthFoodsaver = $this->tester->createFoodsaver(extra_params: [
            'bezirk_id' => $this->region['id'],
        ]);

        // Last incremental calculation was 2 days and 1 hour ago, such that all slots of the last 2 days are included
        $this->tester->haveInDatabase('configuration', [
            'key' => ConfigurationKey::STATISTICS_FOODSAVER_LAST_UPDATE->value,
            'value' => Carbon::now()->subDays(2)->subHour()
        ]);

        $pickupCategoryId = 1011;
        $orgaCategoryId = 1012;
        $engageCategoryId = 1013;

        // Add store categories
        $this->tester->haveInDatabase('fs_betrieb_kategorie', [
            'id' => $pickupCategoryId,
            'name' => 'Test Pickup Category',
            'type' => 0,
        ]);
        $this->tester->haveInDatabase('fs_betrieb_kategorie', [
            'id' => $orgaCategoryId,
            'name' => 'Test Orga Category',
            'type' => 1,
        ]);
        $this->tester->haveInDatabase('fs_betrieb_kategorie', [
            'id' => $engageCategoryId,
            'name' => 'Test Engage Category',
            'type' => 2,
        ]);

        // Add stores
        $pickupStore = $this->tester->createStore($this->region['id'], extra_params: [
            'betrieb_kategorie_id' => $pickupCategoryId,
            'abholmenge' => 4,
        ]);
        $pickupStoreWithoutCategory = $this->tester->createStore($this->region['id'], extra_params: [
            'betrieb_kategorie_id' => null,
            'abholmenge' => 2,
        ]);
        $orgaStore = $this->tester->createStore($this->region['id'], extra_params: [
            'betrieb_kategorie_id' => $orgaCategoryId,
            'abholmenge' => 5,
        ]);
        $engageStore = $this->tester->createStore($this->region['id'], extra_params: [
            'betrieb_kategorie_id' => $engageCategoryId,
            'abholmenge' => 8,
        ]);
        $storeWithoutWeight = $this->tester->createStore($this->region['id'], extra_params: [
            'betrieb_kategorie_id' => $pickupCategoryId,
            'abholmenge' => 999,
        ]);

        // Sign in the first foodsaver into several slots
        $this->tester->haveInDatabase('fs_abholer', [
            'foodsaver_id' => $this->foodsaver['id'],
            'betrieb_id' => $pickupStore['id'],
            'date' => date('Y-m-d H:i:s', strtotime('-3 days')),
        ]);
        $this->tester->haveInDatabase('fs_abholer', [
            'foodsaver_id' => $this->foodsaver['id'],
            'betrieb_id' => $pickupStore['id'],
            'date' => date('Y-m-d H:i:s', strtotime('-2 days')),
        ]);
        $this->tester->haveInDatabase('fs_abholer', [
            'foodsaver_id' => $this->foodsaver['id'],
            'betrieb_id' => $pickupStoreWithoutCategory['id'],
            'date' => date('Y-m-d H:i:s', strtotime('-1 day')),
        ]);
        $this->tester->haveInDatabase('fs_abholer', [
            'foodsaver_id' => $this->foodsaver['id'],
            'betrieb_id' => $orgaStore['id'],
            'date' => date('Y-m-d H:i:s', strtotime('-4 days')),
        ]);
        $this->tester->haveInDatabase('fs_abholer', [
            'foodsaver_id' => $this->foodsaver['id'],
            'betrieb_id' => $orgaStore['id'],
            'date' => date('Y-m-d H:i:s', strtotime('-5 days')),
        ]);
        $this->tester->haveInDatabase('fs_abholer', [
            'foodsaver_id' => $this->foodsaver['id'],
            'betrieb_id' => $orgaStore['id'],
            'date' => date('Y-m-d H:i:s', strtotime('-6 days')),
        ]);
        $this->tester->haveInDatabase('fs_abholer', [
            'foodsaver_id' => $this->foodsaver['id'],
            'betrieb_id' => $engageStore['id'],
            'date' => date('Y-m-d H:i:s', strtotime('-7 days')),
        ]);
        $this->tester->haveInDatabase('fs_abholer', [
            'foodsaver_id' => $this->foodsaver['id'],
            'betrieb_id' => $engageStore['id'],
            'date' => date('Y-m-d H:i:s', strtotime('-8 days')),
        ]);
        $this->tester->haveInDatabase('fs_abholer', [
            'foodsaver_id' => $this->foodsaver['id'],
            'betrieb_id' => $engageStore['id'],
            'date' => date('Y-m-d H:i:s', strtotime('-9 days')),
        ]);
        $this->tester->haveInDatabase('fs_abholer', [
            'foodsaver_id' => $this->foodsaver['id'],
            'betrieb_id' => $engageStore['id'],
            'date' => date('Y-m-d H:i:s', strtotime('-10 days')),
        ]);
        $this->tester->haveInDatabase('fs_abholer', [
            'foodsaver_id' => $this->foodsaver['id'],
            'betrieb_id' => $pickupStore['id'],
            'date' => date('Y-m-d H:i:s', strtotime('+1 day')),
        ]);
        $this->tester->haveInDatabase('fs_abholer', [
            'foodsaver_id' => $this->foodsaver['id'],
            'betrieb_id' => $storeWithoutWeight['id'],
            'date' => date('Y-m-d H:i:s', strtotime('-11 days')),
        ]);

        // Sign in the second foodsaver
        $this->tester->haveInDatabase('fs_abholer', [
            'foodsaver_id' => $this->otherFoodsaver['id'],
            'betrieb_id' => $pickupStore['id'],
            'date' => date('Y-m-d H:i:s', strtotime('-3 days')),
        ]);
        $this->tester->haveInDatabase('fs_abholer', [
            'foodsaver_id' => $this->otherFoodsaver['id'],
            'betrieb_id' => $orgaStore['id'],
            'date' => date('Y-m-d H:i:s', strtotime('-4 days')),
        ]);
        $this->tester->createWallpost($this->otherFoodsaver['id']);

        // Large batch of historical noise for additional foodsavers
        for ($daysAgo = 12; $daysAgo <= 30; ++$daysAgo) {
            $storeForThird = $daysAgo % 2 === 0 ? $pickupStore : $engageStore;
            $this->tester->haveInDatabase('fs_abholer', [
                'foodsaver_id' => $this->thirdFoodsaver['id'],
                'betrieb_id' => $storeForThird['id'],
                'date' => date('Y-m-d H:i:s', strtotime(sprintf('-%d days', $daysAgo))),
            ]);
        }
        for ($daysAgo = 31; $daysAgo <= 45; ++$daysAgo) {
            $storeForFourth = $daysAgo % 3 === 0 ? $orgaStore : $pickupStoreWithoutCategory;
            $this->tester->haveInDatabase('fs_abholer', [
                'foodsaver_id' => $this->fourthFoodsaver['id'],
                'betrieb_id' => $storeForFourth['id'],
                'date' => date('Y-m-d H:i:s', strtotime(sprintf('-%d days', $daysAgo))),
            ]);
        }
        // Future pickups should be excluded for all users
        $this->tester->haveInDatabase('fs_abholer', [
            'foodsaver_id' => $this->thirdFoodsaver['id'],
            'betrieb_id' => $pickupStore['id'],
            'date' => date('Y-m-d H:i:s', strtotime('+2 days')),
        ]);
        $this->tester->haveInDatabase('fs_abholer', [
            'foodsaver_id' => $this->fourthFoodsaver['id'],
            'betrieb_id' => $engageStore['id'],
            'date' => date('Y-m-d H:i:s', strtotime('+3 days')),
        ]);

        // Create additional data: forum posts, wall posts, bananas, and buddies
        $this->tester->addForumThread($this->region['id'], $this->foodsaver['id']);
        $this->tester->createWallpost($this->foodsaver['id']);

        $bananaGiverA = $this->otherFoodsaver;
        $bananaGiverB = $this->tester->createFoodsaver();
        $this->tester->giveBanana($bananaGiverA['id'], $this->foodsaver['id']);
        $this->tester->giveBanana($bananaGiverB['id'], $this->foodsaver['id']);

        $buddyA = $this->tester->createFoodsaver();
        $buddyB = $this->tester->createFoodsaver();
        $this->tester->addBuddy($this->foodsaver['id'], $buddyA['id'], true);
        $this->tester->addBuddy($this->foodsaver['id'], $buddyB['id'], false);
        $this->tester->addBuddy($this->otherFoodsaver['id'], $this->foodsaver['id'], true);
        $this->tester->addBuddy($this->thirdFoodsaver['id'], $this->foodsaver['id'], true);
        $this->tester->addBuddy($this->thirdFoodsaver['id'], $this->otherFoodsaver['id'], true);
        $this->tester->addBuddy($this->fourthFoodsaver['id'], $this->thirdFoodsaver['id'], false);

        $this->tester->createWallpost($this->thirdFoodsaver['id']);
        $this->tester->createWallpost($this->thirdFoodsaver['id']);
        $this->tester->createWallpost($this->fourthFoodsaver['id']);
        $this->tester->giveBanana($this->foodsaver['id'], $this->thirdFoodsaver['id']);
        $this->tester->giveBanana($this->otherFoodsaver['id'], $this->thirdFoodsaver['id']);
        $this->tester->giveBanana($this->thirdFoodsaver['id'], $this->fourthFoodsaver['id']);
    }

    /**
     * With full recalculation.
     */
    public function testUpdateFoodsaverStatsCalculatesAllCounters(): void
    {
        $this->gateway->updateFoodsaverStats();
        $this->gateway->updateFoodsaverIterativeStats(true);

        $this->tester->seeInDatabase('fs_foodsaver', [
            'id' => $this->foodsaver['id'],
            'stat_fetchcount' => 4,
            'stat_fetchweight' => 34,
            'stat_givecount' => 3,
            'stat_engagecount' => 4,
            'stat_postcount' => 2,
            'stat_bananacount' => 2,
            'stat_buddycount' => 1,
        ]);

        $this->tester->seeInDatabase('fs_foodsaver', [
            'id' => $this->otherFoodsaver['id'],
            'stat_fetchcount' => 1,
            'stat_fetchweight' => 15,
            'stat_givecount' => 1,
            'stat_engagecount' => 0,
            'stat_postcount' => 1,
            'stat_buddycount' => 1,
        ]);

        $this->tester->seeInDatabase('fs_foodsaver', [
            'id' => $this->thirdFoodsaver['id'],
            'stat_fetchcount' => 10,
            'stat_fetchweight' => 150,
            'stat_givecount' => 0,
            'stat_engagecount' => 9,
            'stat_postcount' => 2,
            'stat_bananacount' => 2,
            'stat_buddycount' => 2,
        ]);

        $this->tester->seeInDatabase('fs_foodsaver', [
            'id' => $this->fourthFoodsaver['id'],
            'stat_fetchcount' => 10,
            'stat_fetchweight' => 40,
            'stat_givecount' => 5,
            'stat_engagecount' => 0,
            'stat_postcount' => 1,
            'stat_bananacount' => 1,
            'stat_buddycount' => 0,
        ]);
    }

    /**
     * Full recalculation of forum posts, wall posts, bananas, and buddies.
     * Incremental calculation of fetch count, fetch weight, give count, engage count.
     */
    public function testUpdateFoodsaverStatsIncremental(): void
    {
        $previousValues = $this->tester->grabEntryFromDatabase('fs_foodsaver', ['id' => $this->foodsaver['id']]);

        $this->gateway->updateFoodsaverStats();
        $this->gateway->updateFoodsaverIterativeStats(false);

        $this->tester->seeInDatabase('fs_foodsaver', [
            'id' => $this->foodsaver['id'],
            'stat_fetchcount' => $previousValues['stat_fetchcount'] + 2,
            'stat_fetchweight' => $previousValues['stat_fetchweight'] + 19,
            'stat_givecount' => $previousValues['stat_givecount'],
            'stat_engagecount' => $previousValues['stat_engagecount'],
            'stat_postcount' => 2,
            'stat_bananacount' => 2,
            'stat_buddycount' => 1,
        ]);

        $this->tester->seeInDatabase('fs_foodsaver', [
            'id' => $this->otherFoodsaver['id'],
            'stat_fetchcount' => 0,
            'stat_fetchweight' => 0,
            'stat_givecount' => 0,
            'stat_engagecount' => 0,
            'stat_postcount' => 1,
            'stat_buddycount' => 1,
        ]);

        $this->tester->seeInDatabase('fs_foodsaver', [
            'id' => $this->thirdFoodsaver['id'],
            'stat_fetchcount' => 0,
            'stat_fetchweight' => 0,
            'stat_givecount' => 0,
            'stat_engagecount' => 0,
            'stat_postcount' => 2,
            'stat_bananacount' => 2,
            'stat_buddycount' => 2,
        ]);

        $this->tester->seeInDatabase('fs_foodsaver', [
            'id' => $this->fourthFoodsaver['id'],
            'stat_fetchcount' => 0,
            'stat_fetchweight' => 0,
            'stat_givecount' => 0,
            'stat_engagecount' => 0,
            'stat_postcount' => 1,
            'stat_bananacount' => 1,
            'stat_buddycount' => 0,
        ]);
    }
}
