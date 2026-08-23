<?php

declare(strict_types=1);

namespace Tests\Unit;

use Carbon\Carbon;
use Codeception\Test\Unit;
use DateTime;
use Exception;
use Faker\Factory;
use Faker\Generator;
use Foodsharing\Modules\Core\DBConstants\Store\CooperationStatus;
use Foodsharing\Modules\Core\DBConstants\StoreTeam\MembershipStatus;
use Foodsharing\Modules\Core\DBConstants\Unit\UnitType;
use Foodsharing\Modules\Core\DTO\GeoLocation;
use Foodsharing\Modules\Core\Pagination;
use Foodsharing\Modules\Message\MessageGateway;
use Foodsharing\Modules\Region\DTO\MinimalRegionIdentifier;
use Foodsharing\Modules\Store\DTO\Store;
use Foodsharing\Modules\Store\StoreGateway;
use Foodsharing\Modules\Store\TeamStatus;
use Tests\Support\UnitTester;

class StoreGatewayTest extends Unit
{
    protected UnitTester $tester;
    private Generator $faker;
    private StoreGateway $gateway;

    private array $store;
    private array $foodsaver;
    private array $region;

    /**
     * @throws Exception
     */
    private function storeData($status = 'none'): array
    {
        return [
            'id' => $this->store['id'],
            'name' => $this->store['name'],
            'region_name' => $this->region['name'],
            'betrieb_kategorie_id' => $this->store['betrieb_kategorie_id'],
            'kette_id' => $this->store['kette_id'],
            'betrieb_status_id' => $this->store['betrieb_status_id'],
            'ansprechpartner' => $this->store['ansprechpartner'],
            'fax' => $this->store['fax'],
            'telefon' => $this->store['telefon'],
            'email' => $this->store['email'],
            'geo' => implode(', ', [$this->store['lat'], $this->store['lon']]),
            'anschrift' => $this->store['str'],
            'str' => $this->store['str'],
            'plz' => $this->store['plz'],
            'stadt' => $this->store['stadt'],
            'added' => (new DateTime($this->store['added']))->format('Y-m-d'),
            'verantwortlich' => ($status === 'team') ? 0 : null,
            'active' => ($status === 'team') ? 1 : null,
        ];
    }

    final public function _before(): void
    {
        $this->faker = Factory::create('de_DE');

        $this->gateway = $this->tester->get(StoreGateway::class);
        $this->messageGateway = $this->tester->get(MessageGateway::class);
        $this->region = $this->tester->createRegion();
        $this->store = $this->tester->createStore($this->region['id']);
        $this->foodsaver = $this->tester->createFoodsaver();
    }

    public function testAddNewStore(): void
    {
        $storeDTO = new Store(
            new GeoLocation(51.5367827, 9.9258967)
        );
        $storeDTO->name = 'StoreGatewayTestbetrieb';
        $storeDTO->region = new MinimalRegionIdentifier($this->region['id']);
        $storeDTO->address->street = 'Bahnhofsplatz 1';
        $storeDTO->address->postalCode = '37073';
        $storeDTO->address->city = 'Göttingen';
        $storeDTO->publicInfo = 'Testeintrag im Feld öffentliche Information';
        $storeDTO->createdAt = Carbon::now();
        $storeDTO->updatedAt = Carbon::now();

        $teamConversationId = $this->messageGateway->createConversation([], true);
        $standbyConversationId = $this->messageGateway->createConversation([], true);

        $storeId = $this->gateway->addStore($storeDTO, $teamConversationId, $standbyConversationId);

        $this->assertIsInt($storeId);
        $this->assertTrue($storeId !== 0);
    }

    public function testExistChain(): void
    {
        $this->tester->haveInDatabase('fs_chain', ['id' => 2, 'name' => 'Chain']);

        $this->assertTrue($this->gateway->existStoreChain(2));
        $this->assertFalse($this->gateway->existStoreChain(3));
    }

    public function testIsInTeam(): void
    {
        $this->assertEquals(
            TeamStatus::NoMember,
            $this->gateway->getUserTeamStatus($this->foodsaver['id'], $this->store['id'])
        );

        $this->tester->addStoreTeam($this->store['id'], $this->foodsaver['id']);
        $this->assertEquals(
            TeamStatus::Member,
            $this->gateway->getUserTeamStatus($this->foodsaver['id'], $this->store['id'])
        );

        $coordinator = $this->tester->createStoreCoordinator();
        $this->tester->addStoreTeam($this->store['id'], $coordinator['id'], true);
        $this->assertEquals(
            TeamStatus::Coordinator,
            $this->gateway->getUserTeamStatus($coordinator['id'], $this->store['id'])
        );

        $waiter = $this->tester->createFoodsaver();
        $this->tester->addStoreTeam($this->store['id'], $waiter['id'], false, true);
        $this->assertEquals(
            TeamStatus::WaitingList,
            $this->gateway->getUserTeamStatus($waiter['id'], $this->store['id'])
        );
    }

    public function testNullForCooperationStatus()
    {
        $store = $this->tester->createStore($this->region['id'], null, null, ['begin' => '0000-00-00']);
        $readStore = $this->gateway->getStore($store['id']);
        $this->assertNull($readStore->cooperationStart);
    }

    public function testListStoresInRegionStoreContent(): void
    {
        $region = $this->tester->createRegion();
        $storeAdded = new DateTime();
        $storeStatusUpdate = new DateTime();
        $store = $this->tester->createStore($region['id'], null, null, ['added' => $storeAdded, 'status_date' => $storeStatusUpdate]);

        $listOfStores = $this->gateway->listStoresInRegion($region['id'], true);
        $this->assertIsArray($listOfStores);
        $this->assertEquals(1, count($listOfStores));

        $dbStore = $listOfStores[0];
        $this->assertEquals($store['id'], $dbStore->id);
        $this->assertEquals($store['name'], $dbStore->name);
        $this->assertEquals($store['bezirk_id'], $dbStore->region->id);
        $this->assertEquals($store['lat'], $dbStore->location->lat);
        $this->assertEquals($store['lon'], $dbStore->location->lon);
        $this->assertEquals($store['str'], $dbStore->address->street);
        $this->assertEquals($store['plz'], $dbStore->address->postalCode);
        $this->assertEquals($store['stadt'], $dbStore->address->city);
        $this->assertEquals($store['public_info'], $dbStore->publicInfo);
        $this->assertEquals($store['public_time'], $dbStore->publicTime->value);
        $this->assertEquals($store['kette_id'], $dbStore->chain ? $dbStore->chain->id : null);
        $this->assertEquals($store['betrieb_kategorie_id'], $dbStore->category ? $dbStore->category->id : null);
        $this->assertEquals($store['betrieb_status_id'], $dbStore->cooperationStatus->value);
        $this->assertEquals($store['besonderheiten'], $dbStore->description);
        $this->assertEquals($store['presse'], $dbStore->publicity->value);
        $this->assertEquals($store['sticker'], $dbStore->showsSticker->value);
        $this->assertEquals($storeAdded->format('Y-m-d'), $dbStore->createdAt->format('Y-m-d'));
        $this->assertEquals($storeStatusUpdate->format('Y-m-d'), $dbStore->updatedAt->format('Y-m-d'));
    }

    public function testListStoresInRegionStoreContentIsNull(): void
    {
        $region = $this->tester->createRegion();
        $storeAdded = new DateTime();
        $storeStatusUpdate = new DateTime();
        $store = $this->tester->createStore($region['id'], null, null, ['begin' => null, 'besonderheiten' => null, 'team_status' => 1, 'added' => $storeAdded, 'status_date' => $storeStatusUpdate]);

        $listOfStores = $this->gateway->listStoresInRegion($region['id'], true);
        $this->assertIsArray($listOfStores);
        $this->assertEquals(1, count($listOfStores));

        $dbStore = $listOfStores[0];
        $this->assertEquals($store['id'], $dbStore->id);
        $this->assertEquals($store['name'], $dbStore->name);
        $this->assertEquals($store['bezirk_id'], $dbStore->region->id);
        $this->assertEquals($store['lat'], $dbStore->location->lat);
        $this->assertEquals($store['lon'], $dbStore->location->lon);
        $this->assertEquals($store['str'], $dbStore->address->street);
        $this->assertEquals($store['plz'], $dbStore->address->postalCode);
        $this->assertEquals($store['stadt'], $dbStore->address->city);
        $this->assertEquals($store['public_info'], $dbStore->publicInfo);
        $this->assertEquals($store['public_time'], $dbStore->publicTime->value);
        $this->assertEquals($store['begin'], $dbStore->cooperationStart);
        $this->assertEquals($store['kette_id'], $dbStore->chain ? $dbStore->chain->id : null);
        $this->assertEquals($store['betrieb_kategorie_id'], $dbStore->category ? $dbStore->category->id : null);
        $this->assertEquals($store['betrieb_status_id'], $dbStore->cooperationStatus->value);
        $this->assertEquals($store['team_status'], $dbStore->teamStatus->value);
        $this->assertEquals($store['besonderheiten'], $dbStore->description);
        $this->assertEquals($store['presse'], $dbStore->publicity->value);
        $this->assertEquals($store['sticker'], $dbStore->showsSticker->value);
        $this->assertEquals($storeAdded->format('Y-m-d'), $dbStore->createdAt->format('Y-m-d'));
        $this->assertEquals($storeStatusUpdate->format('Y-m-d'), $dbStore->updatedAt->format('Y-m-d'));
    }

    /**
     * Productive database contains "null" values in updateAt field.
     * The newer create and update function do not allow this but it is still present.
     * The read should be robust to read it and forward it correct.
     */
    public function testStoreUpdateAtNullShouldBeReplacedByCreatedAt(): void
    {
        $region = $this->tester->createRegion();
        // `added` is a date column, so only the day is stored and read back
        $creationDay = new DateTime();
        $this->tester->createStore($region['id'], null, null, ['added' => $creationDay, 'status_date' => null]);

        $listOfStores = $this->gateway->listStoresInRegion($region['id'], true);
        $this->assertEquals(1, count($listOfStores));

        $this->assertEquals($creationDay->format('Y-m-d'), $listOfStores[0]->updatedAt->format('Y-m-d'));
        $this->assertEquals($listOfStores[0]->createdAt, $listOfStores[0]->updatedAt);
    }

    public function testBeginSetToNullForCompatibility()
    {
        $store = $this->tester->createStore($this->region['id']);
        $patch = $this->gateway->getStore($store['id']);
        $patch->cooperationStart = null;
        $this->gateway->updateStoreData($patch);

        $this->tester->seeInDatabase('fs_betrieb', ['begin' => null, 'id' => $store['id']]);
    }

    /**
     * Productive database contains "null" values in public information
     * The newer create and update function do not allow this but it is still present.
     * The read should be robust to read it and forward it correct.
     */
    public function testPublicInformationMissing(): void
    {
        $region = $this->tester->createRegion();
        $this->tester->createStore($region['id'], null, null, ['public_info' => null]);

        $listOfStores = $this->gateway->listStoresInRegion($region['id'], true);
        $this->assertEquals(1, count($listOfStores));

        $this->assertEquals($listOfStores[0]->publicInfo, '');
    }

    /**
     * Productive database contains "null" values in public information
     * The newer create and update function do not allow this but it is still present.
     * The read should be robust to read it and forward it correct.
     */
    public function testBetriebsCategoryMissing(): void
    {
        $region = $this->tester->createRegion();
        $this->tester->createStore($region['id'], null, null, ['betrieb_kategorie_id' => null]);

        $listOfStores = $this->gateway->listStoresInRegion($region['id'], true);
        $this->assertEquals(1, count($listOfStores));

        $this->assertEquals($listOfStores[0]->category, null);
    }

    public function testlistStoresInRegionWithSubRegions(): void
    {
        $regionRelatedRegion = $this->tester->createRegion();
        $this->tester->createStore($regionRelatedRegion['id']);
        $this->tester->createStore($regionRelatedRegion['id']);

        $regionTop = $this->tester->createRegion(null, ['type' => UnitType::CITY]);
        $regionChild = $this->tester->createRegion(null, ['parent_id' => $regionTop['id'], 'type' => UnitType::PART_OF_TOWN]);
        $store1 = $this->tester->createStore($regionTop['id']);
        $store2 = $this->tester->createStore($regionTop['id']);
        $store3 = $this->tester->createStore($regionChild['id']);
        $store4 = $this->tester->createStore($regionChild['id']);

        $listOfStores = $this->gateway->listStoresInRegion($regionTop['id'], true);
        $this->assertIsArray($listOfStores);
        $this->assertEquals(4, count($listOfStores));
        $this->assertContainsOnlyInstancesOf(Store::class, $listOfStores);
        $storeIds = array_map(fn ($store) => $store->id, $listOfStores);
        $this->assertContainsEquals($store1['id'], $storeIds);
        $this->assertContainsEquals($store2['id'], $storeIds);
        $this->assertContainsEquals($store3['id'], $storeIds);
        $this->assertContainsEquals($store4['id'], $storeIds);
    }

    public function testlistStoresInRegionWithoutSubRegions(): void
    {
        $regionRelatedRegion = $this->tester->createRegion();
        $this->tester->createStore($regionRelatedRegion['id']);
        $this->tester->createStore($regionRelatedRegion['id']);

        $regionTop = $this->tester->createRegion(null, ['type' => UnitType::CITY]);
        $regionChild = $this->tester->createRegion(null, ['parent_id' => $regionTop['id'], 'type' => UnitType::PART_OF_TOWN]);
        $store1 = $this->tester->createStore($regionTop['id']);
        $store2 = $this->tester->createStore($regionTop['id']);
        $store3 = $this->tester->createStore($regionChild['id']);
        $store4 = $this->tester->createStore($regionChild['id']);

        $listOfStores = $this->gateway->listStoresInRegion($regionTop['id'], false);
        $this->assertIsArray($listOfStores);
        $this->assertEquals(2, count($listOfStores));
        $this->assertContainsOnlyInstancesOf(Store::class, $listOfStores);
        $storeIds = array_map(fn ($store) => $store->id, $listOfStores);
        $this->assertContainsEquals($store1['id'], $storeIds);
        $this->assertContainsEquals($store2['id'], $storeIds);
        $this->assertNotContainsEquals($store3['id'], $storeIds);
        $this->assertNotContainsEquals($store4['id'], $storeIds);
    }

    public function testlistStoresInRegionWithPagination(): void
    {
        $region = $this->tester->createRegion(null, ['type' => UnitType::CITY]);
        $store1 = $this->tester->createStore($region['id']);
        $store2 = $this->tester->createStore($region['id']);
        $store3 = $this->tester->createStore($region['id']);
        $store4 = $this->tester->createStore($region['id']);
        $store5 = $this->tester->createStore($region['id']);

        // results are ordered by store id ascending
        $allIds = [$store1['id'], $store2['id'], $store3['id'], $store4['id'], $store5['id']];
        sort($allIds);

        // first page: limit 2, offset 0
        $firstPage = $this->gateway->listStoresInRegion($region['id'], true, Pagination::create(2, 0));
        $this->assertEquals(2, count($firstPage));
        $this->assertContainsOnlyInstancesOf(Store::class, $firstPage);
        $this->assertEquals(array_slice($allIds, 0, 2), array_map(fn ($store) => $store->id, $firstPage));

        // second page: limit 2, offset 2
        $secondPage = $this->gateway->listStoresInRegion($region['id'], true, Pagination::create(2, 2));
        $this->assertEquals(2, count($secondPage));
        $this->assertEquals(array_slice($allIds, 2, 2), array_map(fn ($store) => $store->id, $secondPage));

        // offset beyond the last page yields the remaining single store
        $thirdPage = $this->gateway->listStoresInRegion($region['id'], true, Pagination::create(2, 4));
        $this->assertEquals(1, count($thirdPage));
        $this->assertEquals(array_slice($allIds, 4, 1), array_map(fn ($store) => $store->id, $thirdPage));

        // no pagination returns all stores
        $all = $this->gateway->listStoresInRegion($region['id'], true);
        $this->assertEquals(5, count($all));
    }

    public function testUpdateStoreRegion(): void
    {
        $newRegion = $this->tester->createRegion();

        $updates = $this->gateway->updateStoreRegion($this->store['id'], $newRegion['id']);

        $this->tester->seeInDatabase('fs_betrieb', ['bezirk_id' => $newRegion['id'], 'id' => $this->store['id']]);
    }

    public function testGetTeamConversation(): void
    {
        $conversationId = $this->gateway->getBetriebConversation($this->store['id']);

        $this->tester->assertEquals($this->store['team_conversation_id'], $conversationId);
    }

    public function testGetSpringerConversation(): void
    {
        $conversationId = $this->gateway->getBetriebConversation($this->store['id'], true);

        $this->tester->assertEquals($this->store['springer_conversation_id'], $conversationId);
    }

    public function testFoodsaverRelatedStoreMembershipStatus(): void
    {
        $store1 = $this->tester->createStore(
            $this->region['id'], null, null, ['betrieb_status_id' => CooperationStatus::COOPERATION_ESTABLISHED->value]
        );
        $store2 = $this->tester->createStore(
            $this->region['id'], null, null, ['betrieb_status_id' => CooperationStatus::COOPERATION_ESTABLISHED->value]
        );
        $store3 = $this->tester->createStore(
            $this->region['id'], null, null, ['betrieb_status_id' => CooperationStatus::COOPERATION_ESTABLISHED->value]
        );
        $store4 = $this->tester->createStore(
            $this->region['id'], null, null, ['betrieb_status_id' => CooperationStatus::COOPERATION_ESTABLISHED->value]
        );
        $store5 = $this->tester->createStore(
            $this->region['id'], null, null, ['betrieb_status_id' => CooperationStatus::DOES_NOT_WANT_TO_WORK_WITH_US->value]
        );
        $otherUser = $this->tester->createFoodsaver();

        $this->tester->addStoreTeam($store1['id'], $this->foodsaver['id'], true, false, true); // Test coordinator
        $this->tester->addStoreTeam(
            $store2['id'], $this->foodsaver['id'], false, true, true
        ); // Test waiting for membership (JUMPER) - Not shown
        $this->tester->addStoreTeam(
            $store3['id'], $this->foodsaver['id'], false, false, true
        ); // Test membership (MEMBER)
        $this->tester->addStoreTeam(
            $store4['id'], $this->foodsaver['id'], false, false, false
        ); // Test open request confirmed (Pending request) - Not shown
        $this->tester->addStoreTeam(
            $store5['id'], $this->foodsaver['id'], false, false, false
        ); // Test open request confirmed (Pending request) - Not shown
        $this->tester->addStoreTeam(
            $store2['id'], $otherUser['id'], false, false, true
        ); // Test open request confirmed (Pending request)

        $expectation = [
            [
                'store_id' => $store1['id'], 'store_name' => $store1['name'], 'managing' => 1,
                'membership_status' => MembershipStatus::MEMBER,
            ],
            [
                'store_id' => $store3['id'], 'store_name' => $store3['name'], 'managing' => 0,
                'membership_status' => MembershipStatus::MEMBER,
            ],
            [
                'store_id' => $store2['id'], 'store_name' => $store2['name'], 'managing' => 0,
                'membership_status' => MembershipStatus::JUMPER,
            ],
            [
                'store_id' => $store4['id'], 'store_name' => $store4['name'], 'managing' => 0,
                'membership_status' => MembershipStatus::APPLIED_FOR_TEAM,
            ],
        ];
        usort($expectation, function ($a, $b) {
            if ($a['managing'] == $b['managing']) {
                if ($a['membership_status'] == $b['membership_status']) {
                    if ($a['store_id'] == $b['store_id']) {
                        return 0;
                    }
                    if ($a['store_id'] < $b['store_id']) {
                        return -1;
                    }

                    return 1;
                }
                if ($a['membership_status'] < $b['membership_status']) {
                    return -1;
                }

                return 1;
            }
            if ($a['managing'] > $b['managing']) {
                return -1;
            }

            return 1;
        });
        $result = $this->gateway->listAllStoreTeamMembershipsForFoodsaver(
            $this->foodsaver['id'], [
                CooperationStatus::UNCLEAR, CooperationStatus::NO_CONTACT, CooperationStatus::IN_NEGOTIATION,
                CooperationStatus::COOPERATION_ESTABLISHED,
                CooperationStatus::PERMANENTLY_CLOSED,
            ]
        );
        $this->assertEquals(4, count($result));
        $this->assertEquals($expectation[0]['store_id'], $result[0]->id);
        $this->assertEquals($expectation[0]['store_name'], $result[0]->name);
        $this->assertEquals($expectation[0]['managing'], $result[0]->isManaging);
        $this->assertEquals($expectation[0]['membership_status'], $result[0]->membershipStatus);

        $this->assertEquals($expectation[1]['store_id'], $result[1]->id);
        $this->assertEquals($expectation[1]['store_name'], $result[1]->name);
        $this->assertEquals($expectation[1]['managing'], $result[1]->isManaging);
        $this->assertEquals($expectation[1]['membership_status'], $result[1]->membershipStatus);

        $this->assertEquals($expectation[2]['store_id'], $result[2]->id);
        $this->assertEquals($expectation[2]['store_name'], $result[2]->name);
        $this->assertEquals($expectation[2]['managing'], $result[2]->isManaging);
        $this->assertEquals($expectation[2]['membership_status'], $result[2]->membershipStatus);

        $this->assertEquals($expectation[3]['store_id'], $result[3]->id);
        $this->assertEquals($expectation[3]['store_name'], $result[3]->name);
        $this->assertEquals($expectation[3]['managing'], $result[3]->isManaging);
        $this->assertEquals($expectation[3]['membership_status'], $result[3]->membershipStatus);
    }

    public function testStoreCooperationFilterForFoodsaverRelatedStoreMembershipsByStatusNotWantToWorkWithUs(): void
    {
        $store2 = $this->tester->createStore(
            $this->region['id'], null, null, ['betrieb_status_id' => CooperationStatus::COOPERATION_ESTABLISHED->value]
        );
        $store5 = $this->tester->createStore(
            $this->region['id'], null, null, ['betrieb_status_id' => CooperationStatus::DOES_NOT_WANT_TO_WORK_WITH_US->value]
        );
        $otherUser = $this->tester->createFoodsaver();

        $this->tester->addStoreTeam(
            $store5['id'], $this->foodsaver['id'], false, false, false
        ); // Test open request confirmed (Pending request) - Not shown
        $this->tester->addStoreTeam(
            $store2['id'], $otherUser['id'], false, false, true
        ); // Test open request confirmed (Pending request)
        $this->tester->addStoreTeam(
            $store5['id'], $otherUser['id'], false, false, true
        ); // Test open request confirmed (Pending request)

        $result = $this->gateway->listAllStoreTeamMembershipsForFoodsaver(
            $this->foodsaver['id'], [CooperationStatus::DOES_NOT_WANT_TO_WORK_WITH_US]
        );
        $this->assertEquals(1, count($result));
        $this->assertEquals($store5['id'], $result[0]->id);
        $this->assertEquals($store5['name'], $result[0]->name);
        $this->assertFalse($result[0]->isManaging);
        $this->assertEquals(MembershipStatus::APPLIED_FOR_TEAM, $result[0]->membershipStatus);
    }

    public function testListRegionStoresActivePickupRule(): void
    {
        $numStoresWithoutPickupRule = $this->faker->numberBetween(1, 4);
        for ($i = 0; $i < $numStoresWithoutPickupRule; ++$i) {
            $this->tester->createStore($this->region['id'], extra_params: ['use_region_pickup_rule' => 0]);
        }
        $numStoresWithPickupRule = $this->faker->numberBetween(1, 4);
        for ($i = 0; $i < $numStoresWithPickupRule; ++$i) {
            $this->tester->createStore($this->region['id'], extra_params: ['use_region_pickup_rule' => 1]);
        }

        $result = $this->gateway->listRegionStoresActivePickupRule($this->region['id']);
        $this->assertCount($numStoresWithPickupRule, $result);
    }

    public function testAddManagerSetsJoinDate(): void
    {
        // Add non-team-member as manager directly, ensure join date is set
        $this->gateway->addStoreManager($this->store['id'], $this->foodsaver['id']);
        $this->tester->seeInDatabase('fs_betrieb_team', [
            'betrieb_id' => $this->store['id'],
            'foodsaver_id' => $this->foodsaver['id'],
            'verantwortlich' => 1,
            'stat_add_date !=' => null,
        ]);

        // Move join date to the past
        $pastAddDate = (new DateTime('-3 day'))->format('Y-m-d');
        $this->tester->updateInDatabase('fs_betrieb_team', [
            'stat_add_date' => $pastAddDate
        ], [
            'betrieb_id' => $this->store['id'],
            'foodsaver_id' => $this->foodsaver['id'],
        ]);

        // Demote manager to normal team member
        $this->gateway->removeStoreManager($this->store['id'], $this->foodsaver['id']);
        $this->tester->seeInDatabase('fs_betrieb_team', [
            'betrieb_id' => $this->store['id'],
            'foodsaver_id' => $this->foodsaver['id'],
            'verantwortlich' => 0,
            'stat_add_date' => $pastAddDate,
        ]);

        // Make manager again to ensure that the original join date is not updated
        $this->gateway->addStoreManager($this->store['id'], $this->foodsaver['id']);
        $this->tester->seeInDatabase('fs_betrieb_team', [
            'betrieb_id' => $this->store['id'],
            'foodsaver_id' => $this->foodsaver['id'],
            'verantwortlich' => 1,
            'stat_add_date' => $pastAddDate,
        ]);
    }
}
