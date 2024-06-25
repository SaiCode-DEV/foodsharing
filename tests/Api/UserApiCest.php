<?php

declare(strict_types=1);

namespace Tests\Api;

use Carbon\Carbon;
use Codeception\Example;
use Codeception\Util\HttpCode as Http;
use Faker\Factory;
use Tests\Support\ApiTester;

/**
 * Tests for the user api.
 */
class UserApiCest
{
    private $user;
    private $userOrga;
    private $store;
    private $faker;

    private const EMAIL = 'email';
    private const API_USER = 'api/user';
    private const ID = 'id';

    public function _before(ApiTester $I): void
    {
        $this->user = $I->createFoodsaver();
        $this->userOrga = $I->createOrga();

        $group = $I->createWorkingGroup('WG');
        $I->addRegionMember($group['id'], $this->user['id']);
        $I->addRegionMember($group['id'], $this->userOrga['id']);

        $region = $I->createRegion();
        $I->addRegionMember($region['id'], $this->user['id']);
        $I->addRegionMember($region['id'], $this->userOrga['id']);

        $this->store = $I->createStore($region['id']);
        $I->addStoreTeam($this->store['id'], $this->user['id']);

        $this->faker = Factory::create('de_DE');
    }

    public function getUser(ApiTester $I): void
    {
        $testUser = $I->createFoodsaver();
        $I->login($this->user[self::EMAIL]);

        // see your own data
        $I->sendGET(self::API_USER . '/' . $this->user[self::ID]);
        $I->seeResponseCodeIs(Http::OK);
        $I->seeResponseIsJson();

        $I->sendGET(self::API_USER . '/current');
        $I->seeResponseCodeIs(Http::OK);
        $I->seeResponseIsJson();

        // see someone else's data
        $I->sendGET(self::API_USER . '/' . $testUser[self::ID]);
        $I->seeResponseCodeIs(Http::OK);
        $I->seeResponseIsJson();

        // do not see data of a non-existing user
        $I->sendGET(self::API_USER . '/999999999');
        $I->seeResponseCodeIs(Http::NOT_FOUND);
        $I->seeResponseIsJson();
    }

    /**
     * Get also own user details with 'current' instead of ID.
     */
    public function getUserDetailsCurrentWithoutId(ApiTester $I): void
    {
        $I->login($this->user[self::EMAIL]);

        // see your own details
        /* $I->sendGET(self::API_USER . '/' . $this->user[self::ID] . '/details');
        $I->seeResponseCodeIs(Http::OK);
        $I->seeResponseIsJson(); */

        $I->sendGET(self::API_USER . '/current/details');
        $I->seeResponseCodeIs(Http::OK);
        $I->seeResponseIsJson();
    }

    /**
     * TODO: disabled because the /user/{id}/details endpoint is disabled.
     *
     * Do not see details of non-existing user.
     */
    /* public function getUserDetailsNoneExistingUser(ApiTester $I): void
    {
        $I->login($this->user[self::EMAIL]);

        $I->sendGET(self::API_USER . '/999999999/details');
        $I->seeResponseCodeIs(Http::NOT_FOUND);
        $I->seeResponseIsJson();
    } */

    /**
     * TODO: disabled because the /user/{id}/details endpoint is disabled.
     *
     * Check that only limited fields are returned for a none logged in user.
     */
    /* public function getUserDetailsNoUser(ApiTester $I): void
    {
        // no login

        $I->sendGET(self::API_USER . '/' . $this->user[self::ID] . '/details');
        $I->seeResponseCodeIs(Http::OK);
        $I->seeResponseIsJson();
        $I->seeResponseMatchesJsonType([
            'id' => 'integer',
            'firstname' => 'string:regex(~.?~)', // firstname is allowed only as 0 or 1 caracter
            'isVerified' => 'boolean',
            'regionId' => 'integer',
            'regionName' => 'string'
        ]);
        $I->dontSeeResponseContains('lastname');
        $I->dontSeeResponseContains('address');
        $I->dontSeeResponseContains('city');
        $I->dontSeeResponseContains('postcode');
        $I->dontSeeResponseContains('email');
        $I->dontSeeResponseContains('landline');
        $I->dontSeeResponseContains('mobile');
        $I->dontSeeResponseContains('birthday');
        $I->dontSeeResponseContains('aboutMeIntern');
        $I->dontSeeResponseContains('regions');
        $I->dontSeeResponseContains('groups');

        $I->dontSeeResponseContainsJson([
            'stats' => [
                'weight' => 'float|integer',
                'count' => 'float|integer',
            ]
        ]);

        $I->dontSeeResponseContainsJson([
            'coordinates' => [
                'lat',
                'lon',
            ]
        ]);

        $I->dontSeeResponseContainsJson([
            'permissions' => [
                'mayEditUserProfile' => false,
                'mayAdministrateUserProfile' => false
            ]
        ]);
    } */

    /**
     * TODO: disabled because the /user/{id}/details endpoint is disabled.
     *
     * Check that only allowed fields for another user are return in the response.
     */
    /* public function getUserDetailsOfOtherUser(ApiTester $I): void
    {
        $testUser = $I->createFoodsaver();
        $I->login($this->user[self::EMAIL]);

        $I->sendGET(self::API_USER . '/' . $testUser[self::ID] . '/details');
        $I->seeResponseCodeIs(Http::OK);
        $I->seeResponseIsJson();
        $I->seeResponseMatchesJsonType([
            'id' => 'integer',
            'firstname' => 'string',
            'lastname' => 'string',
            'isVerified' => 'boolean',
            'regionId' => 'integer',
            'regionName' => 'string',
            'aboutMePublic' => 'string|null',
        ]);

        $I->dontSeeResponseContains('address');
        $I->dontSeeResponseContains('city');
        $I->dontSeeResponseContains('postcode');
        $I->dontSeeResponseContains('email');
        $I->dontSeeResponseContains('landline');
        $I->dontSeeResponseContains('mobile');
        $I->dontSeeResponseContains('birthday');
        $I->dontSeeResponseContains('aboutMeIntern');
        $I->dontSeeResponseContains('regions');
        $I->dontSeeResponseContains('groups');

        $I->seeResponseMatchesJsonType([
            'stats' => [
                'weight' => 'string|float|integer',
                'count' => 'string|float|integer',
            ]
        ]);

        $I->dontSeeResponseContains('coordinates');

        $I->seeResponseMatchesJsonType([
            'permissions' => [
                'mayEditUserProfile' => 'boolean',
                'mayAdministrateUserProfile' => 'boolean'
            ]
        ]);
    } */

    /**
     * TODO: disabled because the /user/{id}/details endpoint is disabled.
     *
     * Check that only allowed fields of the current user are returned in the response.
     */
    /* public function getUserDetailsFromCurrentUser(ApiTester $I): void
    {
        $I->login($this->user[self::EMAIL]);

        $I->sendGET(self::API_USER . '/' . $this->user[self::ID] . '/details');
        $I->seeResponseCodeIs(Http::OK);
        $I->seeResponseIsJson();
        $I->seeResponseMatchesJsonType([
            'id' => 'integer',
            'firstname' => 'string',
            'lastname' => 'string',
            'isVerified' => 'boolean',
            'regionId' => 'integer',
            'regionName' => 'string',
            'address' => 'string',
            'city' => 'string',
            'postcode' => 'string|integer',
            'email' => 'string:email',
            'landline' => 'string|null',
            'mobile' => 'string|null',
            'birthday' => 'string|date',
            'aboutMeIntern' => 'string|null',
            'aboutMePublic' => 'string|null',
            'gender' => 'integer',
            'regions' => 'array',
            'groups' => 'array'
        ]);

        $I->seeResponseMatchesJsonType([
            'id' => 'integer',
            'name' => 'string',
            'classification' => 'integer',
            'isResponsible' => 'boolean'
        ], '$.regions.*');

        $I->seeResponseMatchesJsonType([
            'id' => 'integer',
            'name' => 'string',
            'isResponsible' => 'boolean'
        ], '$.groups.*');

        $I->seeResponseMatchesJsonType([
            'stats' => [
                'weight' => 'string|float|integer',
                'count' => 'string|float|integer',
            ]
        ]);

        $I->seeResponseMatchesJsonType([
            'coordinates' => [
                'lat' => 'string|float|integer',
                'lon' => 'string|float|integer',
            ]
        ]);

        $I->seeResponseMatchesJsonType([
            'permissions' => [
                'mayEditUserProfile' => 'boolean',
                'mayAdministrateUserProfile' => 'boolean'
            ]
        ]);
    } */

    /**
     * TODO: disabled because the /user/{id}/details endpoint is disabled.
     *
     * Check that all fields of a user are returned for an orga user.
     */
    /* public function getUserDetailsAsOrgaUser(ApiTester $I): void
    {
        $I->login($this->userOrga[self::EMAIL]);

        $I->sendGET(self::API_USER . '/' . $this->user[self::ID] . '/details');
        $I->seeResponseCodeIs(Http::OK);
        $I->seeResponseIsJson();
        $I->seeResponseMatchesJsonType([
            'id' => 'integer',
            'firstname' => 'string',
            'lastname' => 'string',
            'isVerified' => 'boolean',
            'regionId' => 'integer',
            'regionName' => 'string',
            'address' => 'string',
            'city' => 'string',
            'postcode' => 'string|integer',
            'email' => 'string:email',
            'landline' => 'string|null',
            'mobile' => 'string|null',
            'birthday' => 'string|date',
            'aboutMeIntern' => 'string|null',
            'aboutMePublic' => 'string|null',
            'role' => 'integer',
            'position' => 'string|null',
            'gender' => 'integer',
            'regions' => 'array',
            'groups' => 'array'
        ]);

        $I->seeResponseMatchesJsonType([
            'id' => 'integer',
            'name' => 'string',
            'classification' => 'integer',
            'isResponsible' => 'boolean'
        ], '$.regions.*');

        $I->seeResponseMatchesJsonType([
            'id' => 'integer',
            'name' => 'string',
            'isResponsible' => 'boolean'
        ], '$.groups.*');

        $I->seeResponseMatchesJsonType([
            'stats' => [
                'weight' => 'string|float|integer',
                'count' => 'string|float|integer',
            ]
        ]);

        $I->seeResponseMatchesJsonType([
            'coordinates' => [
                'lat' => 'string|float|integer',
                'lon' => 'string|float|integer',
            ]
        ]);

        $I->seeResponseContainsJson([
            'permissions' => [
                'mayEditUserProfile' => true,
                'mayAdministrateUserProfile' => true
            ]
        ]);
    } */

    /**
     * @example["abcd@efgh.com"]
     * @example["test123@somedomain.de"]
     */
    public function canUseEmailForRegistration(ApiTester $I, Example $example): void
    {
        $I->sendPOST(self::API_USER . '/isvalidemail', ['email' => $example[0]]);
        $I->seeResponseCodeIs(Http::OK);
    }

    /**
     * @example["abcd"]
     * @example["abcd@efgh"]
     * @example["abcd@-efgh"]
     */
    public function canNotUseInvalidMailForRegistration(ApiTester $I, Example $example): void
    {
        $I->sendPOST(self::API_USER . '/isvalidemail', ['email' => $example[0]]);
        $I->seeResponseCodeIs(Http::BAD_REQUEST);
        $I->seeResponseIsJson();
        $I->canSeeResponseContainsJson([
            'message' => 'email is not valid'
        ]);
    }

    /**
     * @example["abcd@foodsharing.de"]
     * @example["abcd@foodsharing.network"]
     */
    public function canNotUseFoodsharingEmailForRegistration(ApiTester $I, Example $example): void
    {
        $I->sendPOST(self::API_USER . '/isvalidemail', ['email' => $example[0]]);
        $I->seeResponseCodeIs(Http::OK);
        $I->seeResponseIsJson();
        $I->canSeeResponseContainsJson([
            'valid' => false
        ]);
    }

    public function canNotUseExistingEmailForRegistration(ApiTester $I): void
    {
        // already existing email
        $I->sendPOST(self::API_USER . '/isvalidemail', ['email' => $this->user['email']]);
        $I->seeResponseCodeIs(Http::OK);
        $I->seeResponseIsJson();
        $I->canSeeResponseContainsJson([
            'valid' => false
        ]);

        // not yet existing email
        $email = 'test123@somedomain.de';
        $I->sendPOST(self::API_USER . '/isvalidemail', ['email' => $email]);
        $I->seeResponseCodeIs(Http::OK);
        $I->seeResponseIsJson();
        $I->canSeeResponseContainsJson([
            'valid' => true
        ]);

        $I->createFoodsharer(null, ['email' => $email]);
        $I->sendPOST(self::API_USER . '/isvalidemail', ['email' => $email]);
        $I->seeResponseCodeIs(Http::OK);
        $I->seeResponseIsJson();
        $I->canSeeResponseContainsJson([
            'valid' => false
        ]);
    }

    public function canGiveBanana(ApiTester $I): void
    {
        $testUser = $I->createFoodsaver();
        $I->login($this->user[self::EMAIL]);
        $I->sendPUT(self::API_USER . '/' . $testUser['id'] . '/banana', ['message' => $this->createRandomText(100, 150)]);

        // Check for Bell as well
        $bellIdentifier = 'banana-' . $testUser[self::ID] . '-' . $this->user[self::ID];
        $I->seeInDatabase('fs_bell', ['identifier' => $bellIdentifier]);
        $bellId = $I->grabFromDatabase('fs_bell', 'id', ['identifier' => $bellIdentifier]);
        $I->seeInDatabase('fs_foodsaver_has_bell', [
            'foodsaver_id' => $testUser[self::ID],
            'bell_id' => $bellId,
        ]);

        $I->seeResponseCodeIs(Http::OK);
    }

    public function canNotGiveBananaWithShortMessage(ApiTester $I): void
    {
        $testUser = $I->createFoodsaver();
        $I->login($this->user[self::EMAIL]);
        $I->sendPUT(self::API_USER . '/' . $testUser['id'] . '/banana', ['message' => $this->faker->text(50)]);
        $I->seeResponseCodeIs(Http::BAD_REQUEST);
    }

    public function canNotGiveBananaTwice(ApiTester $I): void
    {
        $testUser = $I->createFoodsaver();
        $I->login($this->user[self::EMAIL]);
        $I->sendPUT(self::API_USER . '/' . $testUser['id'] . '/banana', ['message' => $this->createRandomText(100, 150)]);
        $I->seeResponseCodeIs(Http::OK);
        $I->sendPUT(self::API_USER . '/' . $testUser['id'] . '/banana', ['message' => $this->createRandomText(100, 150)]);
        $I->seeResponseCodeIs(Http::FORBIDDEN);
    }

    public function canNotGiveBananaToMyself(ApiTester $I): void
    {
        $I->login($this->user[self::EMAIL]);
        $I->sendPUT(self::API_USER . '/' . $this->user['id'] . '/banana', ['message' => $this->createRandomText(100, 150)]);
        $I->seeResponseCodeIs(Http::FORBIDDEN);
    }

    private function createRandomText(int $minLength, int $maxLength): string
    {
        $text = $this->faker->text($maxLength);
        while (strlen((string)$text) < $minLength) {
            $text .= ' ' . $this->faker->text(($maxLength + $minLength) / 2 - strlen((string)$text));
        }

        return $text;
    }

    public function canDeleteUser(ApiTester $I): void
    {
        // add user to a pickup slots
        $I->addPicker($this->store['id'], $this->user['id']);
        $I->addPicker($this->store['id'], $this->user['id'], ['confirmed' => 0]);

        // delete user
        $I->login($this->user[self::EMAIL]);
        $I->sendDELETE(self::API_USER . '/' . $this->user['id']);
        $I->seeResponseCodeIs(Http::NO_CONTENT);

        // check that the user is not in the team anymore and that no future slots are assigned to the user
        $I->dontSeeInDatabase('fs_betrieb_team', [
            'betrieb_id' => $this->store['id'],
            'foodsaver_id' => $this->user['id']
        ]);
        $I->dontSeeInDatabase('fs_abholer', [
            'foodsaver_id' => $this->user['id'],
            'betrieb_id' => $this->store['id'],
            'date >' => Carbon::now()->format('Y-m-d H:i:s')
        ]);
    }

    public function canFetchUserNamesWhenLoggedIn(ApiTester $I): void
    {
        $I->sendGet(self::API_USER . '/names/' . $this->userOrga['id'] . '-' . $this->user['id']);
        $I->seeResponseCodeIs(Http::UNAUTHORIZED);
        $I->login($this->user[self::EMAIL]);
        $I->sendGet(self::API_USER . '/names/' . $this->userOrga['id'] . '-' . $this->user['id']);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson([
            ['id' => $this->userOrga['id'], 'name' => $this->userOrga['name']],
            ['id' => $this->user['id'], 'name' => $this->user['name']]
        ]);
    }

}
