<?php

declare(strict_types=1);

namespace Tests\Api;

use Carbon\Carbon;
use Codeception\Example;
use Codeception\Util\HttpCode as Http;
use Tests\Support\ApiTester;

/**
 * Tests for the user api.
 */
/**
 * @group api-group-1
 */
class UserApiCest
{
    private $user;
    private $userOrga;
    private $region;

    private const string EMAIL = 'email';
    private const string API_USER = 'api/users';
    private const string ID = 'id';

    public function _before(ApiTester $I): void
    {
        $this->user = $I->createFoodsaver();
        $this->userOrga = $I->createOrga();

        $group = $I->createWorkingGroup('WG');
        $I->addRegionMember($group['id'], $this->user['id']);
        $I->addRegionMember($group['id'], $this->userOrga['id']);

        $this->region = $I->createRegion(fillMailbox: false);
        $I->addRegionMember($this->region['id'], $this->user['id']);
        $I->addRegionMember($this->region['id'], $this->userOrga['id']);
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
        $I->sendGET(self::API_USER . '/current/details');
        $I->seeResponseCodeIs(Http::OK);
        $I->seeResponseIsJson();
    }

    /**
     * @example["abcd@efgh.com"]
     * @example["test123@somedomain.de"]
     */
    public function canUseEmailForRegistration(ApiTester $I, Example $example): void
    {
        $I->sendPOST(self::API_USER . '/registration/email-checker', ['email' => $example[0]]);
        $I->seeResponseCodeIs(Http::OK);
    }

    /**
     * @example["abcd"]
     * @example["abcd@efgh"]
     * @example["abcd@-efgh"]
     */
    public function canNotUseInvalidMailForRegistration(ApiTester $I, Example $example): void
    {
        $I->sendPOST(self::API_USER . '/registration/email-checker', ['email' => $example[0]]);
        $I->seeResponseCodeIs(Http::UNPROCESSABLE_ENTITY);
    }

    /**
     * @example["abcd@foodsharing.de"]
     * @example["abcd@foodsharing.network"]
     */
    public function canNotUseFoodsharingEmailForRegistration(ApiTester $I, Example $example): void
    {
        $I->sendPOST(self::API_USER . '/registration/email-checker', ['email' => $example[0]]);
        $I->seeResponseCodeIs(Http::OK);
        $I->seeResponseIsJson();
        $I->canSeeResponseContainsJson([
            'isValid' => false
        ]);
    }

    public function canNotUseExistingEmailForRegistration(ApiTester $I): void
    {
        // already existing email
        $I->sendPOST(self::API_USER . '/registration/email-checker', ['email' => $this->user['email']]);
        $I->seeResponseCodeIs(Http::OK);
        $I->seeResponseIsJson();
        $I->canSeeResponseContainsJson([
            'isValid' => false
        ]);

        // not yet existing email
        $email = 'test123@somedomain.de';
        $I->sendPOST(self::API_USER . '/registration/email-checker', ['email' => $email]);
        $I->seeResponseCodeIs(Http::OK);
        $I->seeResponseIsJson();
        $I->canSeeResponseContainsJson([
            'isValid' => true
        ]);

        $I->createFoodsharer(null, ['email' => $email]);
        $I->sendPOST(self::API_USER . '/registration/email-checker', ['email' => $email]);
        $I->seeResponseCodeIs(Http::OK);
        $I->seeResponseIsJson();
        $I->canSeeResponseContainsJson([
            'isValid' => false
        ]);
    }

    public function canDeleteUser(ApiTester $I): void
    {
        $store = $I->createStore($this->region['id']);
        $I->addStoreTeam($store['id'], $this->user['id']);

        // add user to a pickup slots
        $I->addPicker($store['id'], $this->user['id']);
        $I->addPicker($store['id'], $this->user['id'], ['confirmed' => 0]);

        // delete user without password should fail
        $I->login($this->user[self::EMAIL]);
        $I->sendDELETE(self::API_USER . '/' . $this->user['id'], ['password' => '124']);
        $I->seeResponseCodeIs(Http::UNAUTHORIZED);

        // delete user
        $I->login($this->user[self::EMAIL]);
        $I->sendDELETE(self::API_USER . '/' . $this->user['id'], ['password' => 'password']);
        $I->seeResponseCodeIs(Http::OK);

        // check that the user is not in the team anymore and that no future slots are assigned to the user
        $I->dontSeeInDatabase('fs_betrieb_team', [
            'betrieb_id' => $store['id'],
            'foodsaver_id' => $this->user['id']
        ]);
        $I->dontSeeInDatabase('fs_abholer', [
            'foodsaver_id' => $this->user['id'],
            'betrieb_id' => $store['id'],
            'date >' => Carbon::now()->format('Y-m-d H:i:s')
        ]);
    }

    public function canOnlyFetchAbbreviatedUserNamesWhenLoggedOut(ApiTester $I): void
    {
        $I->sendGet(self::API_USER . '/' . $this->userOrga['id'] . '-' . $this->user['id'] . '/names');
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson([
            ['id' => $this->userOrga['id'], 'name' => substr($this->userOrga['name'], 0, 1) . '.'],
            ['id' => $this->user['id'], 'name' => substr($this->user['name'], 0, 1) . '.']
        ]);
    }

    public function canFetchUserNamesWhenLoggedIn(ApiTester $I): void
    {
        $I->login($this->user[self::EMAIL]);
        $I->sendGet(self::API_USER . '/' . $this->userOrga['id'] . '-' . $this->user['id'] . '/names');
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson([
            ['id' => $this->userOrga['id'], 'name' => $this->userOrga['name']],
            ['id' => $this->user['id'], 'name' => $this->user['name']]
        ]);
    }
}
