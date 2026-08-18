<?php

namespace Tests\Api;

use Codeception\Util\HttpCode;
use Faker\Factory;
use Faker\Generator;
use Foodsharing\Modules\Core\DBConstants\Region\RegionIDs;
use Tests\Support\ApiTester;

class StoreChainApiCest
{
    private Generator $faker;
    // admin of the store chain group
    private array $admin;
    // not a member of the group
    private array $user;
    private array $chains;

    public function _before(ApiTester $I): void
    {
        $this->faker = Factory::create('de_DE');

        $this->admin = $I->createFoodsaver();
        $group = $I->createWorkingGroup('AG Betriebsketten', ['id' => RegionIDs::STORE_CHAIN_GROUP]);
        $I->addRegionMember($group['id'], $this->admin['id']);
        $I->addRegionAdmin($group['id'], $this->admin['id']);
        $this->user = $I->createFoodsaver();

        $numChains = $this->faker->numberBetween(5, 10);
        $this->chains = [];
        for ($i = 0; $i < $numChains; ++$i) {
            $this->chains[] = $I->addStoreChain();
        }
    }

    // GET /chains/{id}
    public function canGetSingleStoreChain(ApiTester $I): void
    {
        $chain = $this->faker->randomElement($this->chains);

        $I->login($this->admin['email']);
        $I->sendGET('api/chains/' . $chain['id']);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseContainsJson(['chain' => [
            'id' => $chain['id'],
            'name' => $chain['name'],
            'status' => $chain['status'],
            'headquartersZip' => $chain['headquarters_zip'],
            'headquartersCity' => $chain['headquarters_city'],
            'allowPress' => $chain['allow_press'] == 1,
            'notes' => $chain['notes'],
            'commonStoreInformation' => $chain['common_store_information'],
        ]]);
    }

    public function canNotGetNonExistingStoreChain(ApiTester $I): void
    {
        $I->login($this->admin['email']);
        $I->sendGET('api/chains/999999');
        $I->seeResponseCodeIs(HttpCode::NOT_FOUND);
    }

    // GET /chains
    public function canGetStoreChainList(ApiTester $I): void
    {
        $I->login($this->admin['email']);
        $I->sendGET('api/chains');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseIsJson();
        $chains = $I->grabDataFromResponseByJsonPath('$');
        $I->assertCount(count($this->chains), $chains[0]);
    }

    public function canGetStoreChainListPaginated(ApiTester $I): void
    {
        $number = $this->faker->numberBetween(1, count($this->chains) - 1);
        $I->login($this->admin['email']);
        $I->sendGET('api/chains?limit=' . $number);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseIsJson();
        $chains = $I->grabDataFromResponseByJsonPath('$');
        $I->assertCount($number, $chains[0]);
    }

    // POST /chains
    public function canCreateStoreChain(ApiTester $I): void
    {
        $chain = $this->randomStoreChain($I);

        $I->login($this->admin['email']);
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPOST('api/chains', $chain);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeInDatabase('fs_chain', ['name' => $chain['name']]);
    }

    public function canNotCreateStoreChainWithEmptyName(ApiTester $I): void
    {
        $chain = $this->randomStoreChain($I);
        $chain['name'] = '';

        $I->login($this->admin['email']);
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPOST('api/chains', $chain);
        $I->seeResponseCodeIs(HttpCode::UNPROCESSABLE_ENTITY);
    }

    public function canNotCreateStoreAsUser(ApiTester $I): void
    {
        $chain = $this->randomStoreChain($I);

        $I->login($this->user['email']);
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPOST('api/chains', $chain);
        $I->seeResponseCodeIs(HttpCode::FORBIDDEN);
        $I->dontSeeInDatabase('fs_chain', ['name' => $chain['name']]);
    }

    // PATCH /chains/{id}
    public function canPatchStoreChain(ApiTester $I): void
    {
        $chain = $this->faker->randomElement($this->chains);
        $newValues = $this->randomStoreChain($I);

        $I->login($this->admin['email']);
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH('api/chains/' . $chain['id'], $newValues);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeInDatabase('fs_chain', ['id' => $chain['id'], 'name' => $newValues['name']]);
    }

    public function canNotPatchStoreChainWithoutPermission(ApiTester $I): void
    {
        $chain = $this->faker->randomElement($this->chains);

        $I->login($this->user['email']);
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH('api/chains/' . $chain['id'], $this->randomStoreChain($I));
        $I->seeResponseCodeIs(HttpCode::FORBIDDEN);
        $I->seeInDatabase('fs_chain', ['id' => $chain['id'], 'name' => $chain['name']]);
    }

    public function canNotPatchNonExistingStoreChain(ApiTester $I): void
    {
        $I->login($this->admin['email']);
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH('api/chains/999999', $this->randomStoreChain($I));
        $I->seeResponseCodeIs(HttpCode::NOT_FOUND);
    }

    private function randomStoreChain(ApiTester $I): array
    {
        $thread = $I->addForumThread(RegionIDs::STORE_CHAIN_GROUP, $this->admin['id']);

        return [
            'name' => 'chain_' . $this->faker->company(),
            'status' => random_int(0, 4),
            'headquartersZip' => $this->faker->postcode(),
            'headquartersCity' => $this->faker->city(),
            'headquartersCountry' => $this->faker->country(),
            'modificationDate' => $this->faker->dateTimeThisDecade()->format('Y-m-d H:i:s'),
            'allowPress' => random_int(0, 1) === 1,
            'notes' => $this->faker->realTextBetween(5, 80),
            'commonStoreInformation' => $this->faker->realTextBetween(100, 500),
            'kams' => [],
            'estimatedStoreCount' => $this->faker->numberBetween(2, 100),
            'forumThread' => $thread['id'],
        ];
    }
}
