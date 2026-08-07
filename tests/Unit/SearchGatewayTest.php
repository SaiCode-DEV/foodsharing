<?php

declare(strict_types=1);

namespace Tests\Unit;

use Codeception\Test\Unit;
use Foodsharing\Modules\Core\DBConstants\Region\RegionIDs;
use Foodsharing\Modules\Core\DBConstants\Store\CooperationStatus;
use Foodsharing\Modules\Core\DBConstants\Unit\UnitType;
use Foodsharing\Modules\Search\SearchGateway;
use Tests\Support\UnitTester;

class SearchGatewayTest extends Unit
{
    protected UnitTester $tester;
    protected SearchGateway $gateway;
    protected array $regions;
    protected array $users;
    protected array $groups;
    protected array $stores;
    protected array $sharePoints;
    protected array $chats;

    final public function _before(): void
    {
        $this->gateway = $this->tester->get(SearchGateway::class);

        $regionCountry = $this->tester->createRegion('Deutschland', ['parent_id' => RegionIDs::EUROPE, 'type' => UnitType::COUNTRY, 'has_children' => 1]);
        $regionState1 = $this->tester->createRegion('Sachsen', ['parent_id' => $regionCountry['id'], 'type' => UnitType::FEDERAL_STATE, 'has_children' => 1]);
        $regionState2 = $this->tester->createRegion('Sachsen-Anhalt', ['parent_id' => $regionCountry['id'], 'type' => UnitType::FEDERAL_STATE, 'has_children' => 1]);
        $regionCity1 = $this->tester->createRegion('Dresden', ['parent_id' => $regionState1['id'], 'type' => UnitType::CITY, 'has_children' => 1, 'email' => 'dreeesden']);
        $regionCity2 = $this->tester->createRegion('Freiberg', ['parent_id' => $regionState1['id'], 'type' => UnitType::CITY, 'has_children' => 1]);
        $regionCity3 = $this->tester->createRegion('Magdeburg', ['parent_id' => $regionState2['id'], 'type' => UnitType::CITY, 'has_children' => 1]);
        $regionCity4 = $this->tester->createRegion('Bad Dürrenberg', ['parent_id' => $regionState2['id'], 'type' => UnitType::CITY, 'has_children' => 1]);

        $this->regions = [
            'europe' => ['id' => RegionIDs::EUROPE],
            'country' => $regionCountry,
            'state1' => $regionState1,
            'state2' => $regionState2,
            'city1' => $regionCity1,
            'city2' => $regionCity2,
            'city3' => $regionCity3,
            'city4' => $regionCity4,
        ];

        $this->users = array_combine(
            array_map(fn ($key) => 'user-' . $key, array_keys($this->regions)),
            array_map(fn ($region) => $this->tester->createFoodsaver(null, [
                'name' => 'Nutzer',
                'nachname' => 'Nachname',
                'bezirk_id' => $region['id'],
            ]), $this->regions)
        );
        $this->users['bot-city1'] = $this->tester->createAmbassador(null, [
            'name' => 'Nutzer Bot',
            'nachname' => 'Nachname',
            'bezirk_id' => $this->regions['city1']['id'],
        ]);

        $this->groups = [
            'wg-city1' => $this->tester->createRegion('AG Dresden', ['parent_id' => $this->regions['city1']['id'], 'type' => UnitType::WORKING_GROUP, 'email' => 'ag-mail-dresden']),
            'wg-city2' => $this->tester->createRegion('AG Freiberg', ['parent_id' => $this->regions['city2']['id'], 'type' => UnitType::WORKING_GROUP, 'email' => 'ag-mail-freiberg']),
        ];

        $this->tester->addRegionAdmin($this->regions['city1']['id'], $this->users['bot-city1']['id']);

        $this->stores = [
            'store-city1' => $this->tester->createStore($this->regions['city1']['id'], null, null, ['name' => 'BetriebA', 'betrieb_status_id' => CooperationStatus::COOPERATION_ESTABLISHED->value]),
            'store-city1-closed' => $this->tester->createStore($this->regions['city1']['id'], null, null, ['name' => 'Betrieb geschlossen', 'betrieb_status_id' => CooperationStatus::PERMANENTLY_CLOSED->value]),
            'store-city2' => $this->tester->createStore($this->regions['city2']['id'], null, null, ['name' => 'BetriebB', 'betrieb_status_id' => CooperationStatus::COOPERATION_ESTABLISHED->value]),
        ];

        $this->sharePoints = [
            'fsp-city1' => $this->tester->createFoodSharePoint($this->users['user-city3']['id'], $this->regions['city1']['id'], ['name' => 'FairteilerA']),
            'fsp-city2' => $this->tester->createFoodSharePoint($this->users['user-city3']['id'], $this->regions['city2']['id'], ['name' => 'FairteilerB']),
        ];

        $this->chats = [
            'chat1' => $this->tester->createConversation([$this->users['user-city1']['id'], $this->users['user-city2']['id']], ['last_message_id' => 1, 'last_message' => 'msg']),
            'chat2' => $this->tester->createConversation([$this->users['user-city2']['id'], $this->users['user-city3']['id']], ['last_message_id' => 1, 'last_message' => 'msg']),
        ];
    }

    private function assertCorrectSearchResult($variableName, $expectedElements, $searchResult)
    {
        $this->assertEqualsCanonicalizing(
            array_map(fn ($key) => $this->$variableName[$key]['id'], $expectedElements),
            array_map(fn ($searchResultObj) => $searchResultObj->id, $searchResult)
        );
    }

    public function testSearchRegions()
    {
        // Basic example:
        $this->assertCorrectSearchResult('regions', ['state1', 'state2'], $this->gateway->searchRegions('Sachsen', 1));

        // Not only word start:
        $this->assertCorrectSearchResult('regions', ['city2', 'city4'], $this->gateway->searchRegions('berg', 1));

        // cAsE dOsN't MaTtEr:
        $this->assertCorrectSearchResult('regions', ['city1'], $this->gateway->searchRegions('dRESDEN', 1));

        // Searching for mail adress:
        $this->assertCorrectSearchResult('regions', ['city1'], $this->gateway->searchRegions('dreeesden', 1));
    }

    public function testSearchWorkingGroups()
    {
        // Only find wgs in own regions
        $this->assertCorrectSearchResult('groups', ['wg-city1'], $this->gateway->searchWorkingGroups('ag', $this->users['user-city1']['id'], false));

        // Searching for mail adress:
        $this->assertCorrectSearchResult('groups', ['wg-city1'], $this->gateway->searchWorkingGroups('ag-mail', $this->users['user-city1']['id'], false));

        // except if searching globally:
        $this->assertCorrectSearchResult('groups', ['wg-city1', 'wg-city2'], $this->gateway->searchWorkingGroups('ag', $this->users['user-city1']['id'], true));
    }

    public function testSearchStores()
    {
        // Only find active stores in own regions
        $this->assertCorrectSearchResult('stores', ['store-city1'], $this->gateway->searchStores('Betrieb', $this->users['user-city1']['id'], false, false));

        // Include inactive stores
        $this->assertCorrectSearchResult('stores', ['store-city1', 'store-city1-closed'], $this->gateway->searchStores('Betrieb', $this->users['user-city1']['id'], true, false));

        // Search global
        $this->assertCorrectSearchResult('stores', ['store-city1', 'store-city2'], $this->gateway->searchStores('Betrieb', $this->users['user-city1']['id'], false, true));
    }

    public function testSearchFoodSharePoints()
    {
        // Only find food share points in own regions
        $this->assertCorrectSearchResult('sharePoints', ['fsp-city1'], $this->gateway->searchFoodSharePoints('Fairteiler', $this->users['user-city1']['id'], false));

        // Search global
        $this->assertCorrectSearchResult('sharePoints', ['fsp-city1', 'fsp-city2'], $this->gateway->searchFoodSharePoints('Fairteiler', $this->users['user-city1']['id'], true));
    }

    public function testSearchChats()
    {
        // Only find chats the users is a member in
        $this->assertCorrectSearchResult('chats', ['chat1'], $this->gateway->searchChats('Nutzer', $this->users['user-city1']['id']));
    }

    public function testSearchUsers()
    {
        // Find users in same region and users with common chat:
        $this->assertCorrectSearchResult('users', ['user-city1', 'user-city2', 'bot-city1'], $this->gateway->searchUsers('Nutzer', $this->users['user-city1']['id'], false));

        // Search by last name as bot:
        $this->assertCorrectSearchResult('users', ['user-city1', 'bot-city1'], $this->gateway->searchUsers('Nachname', $this->users['bot-city1']['id'], false));

        // Search global, last name and region name as criteria:
        $this->assertCorrectSearchResult('users', ['user-city3'], $this->gateway->searchUsers('Nachname Magdeburg', $this->users['bot-city1']['id'], true));
    }
}
