<?php

declare(strict_types=1);

namespace Tests\Api;

use Carbon\Carbon;
use Codeception\Example;
use Codeception\Util\HttpCode;
use Faker\Factory;
use Faker\Generator;
use Foodsharing\Modules\Core\DBConstants\Foodsaver\SleepStatus;
use Foodsharing\Modules\Core\DBConstants\Region\RegionIDs;
use Foodsharing\Modules\Core\DBConstants\Unit\UnitType;
use Tests\Support\ApiTester;

/**
 * @group api-group-1
 */
class SettingsApiCest
{
    private Generator $faker;
    private $user;
    private $userAmbassador;
    private $userAmbassadorWithoutRegion;
    private $userOrga;
    private array $userWithPassword;
    private string $passwordOfUser;
    private $region1;
    private $region2;
    private $regionState;

    public function _before(ApiTester $I): void
    {
        $this->faker = Factory::create('de_DE');

        $this->region1 = $I->createRegion(fillMailbox: false);
        $this->region2 = $I->createRegion(fillMailbox: false);
        $this->regionState = $I->createRegion(fillMailbox: false, extra_params: ['type' => UnitType::FEDERAL_STATE]);
        $this->user = $I->createFoodsaver();
        $I->addRegionMember($this->region1['id'], $this->user['id']);

        $this->userAmbassador = $I->createAmbassador();
        $I->addRegionMember($this->region1['id'], $this->userAmbassador['id']);
        $I->addRegionAdmin($this->region1['id'], $this->userAmbassador['id']);

        $this->userAmbassadorWithoutRegion = $I->createAmbassador();
        $I->addRegionMember($this->region2['id'], $this->userAmbassadorWithoutRegion['id']);
        $I->addRegionAdmin($this->region2['id'], $this->userAmbassadorWithoutRegion['id']);

        $this->userOrga = $I->createOrga();

        $this->passwordOfUser = $this->faker->password(8);
        $this->userWithPassword = $I->createFoodsaver($this->passwordOfUser);
    }

    public function canOnlySetSleepStatusWhenLoggedIn(ApiTester $I): void
    {
        $I->sendPATCH('api/users/current/sleep-mode', ['mode' => SleepStatus::NONE]);
        $I->seeResponseCodeIs(HttpCode::UNAUTHORIZED);
        $I->seeInDatabase('fs_foodsaver', [
            'id' => $this->user['id'],
            'sleep_status' => SleepStatus::NONE
        ]);
    }

    public function canSetSleepStatus(ApiTester $I): void
    {
        // full sleep mode
        $I->login($this->user['email']);
        $I->sendPATCH('api/users/current/sleep-mode', ['mode' => SleepStatus::FULL]);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeInDatabase('fs_foodsaver', [
            'id' => $this->user['id'],
            'sleep_status' => SleepStatus::FULL
        ]);

        // temporary sleep mode
        $I->login($this->user['email']);
        $I->sendPATCH('api/users/current/sleep-mode', [
            'mode' => SleepStatus::TEMP,
            'from' => Carbon::today()->addDay()->format('Y-m-d'),
            'to' => Carbon::today()->addWeek()->format('Y-m-d')
        ]);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeInDatabase('fs_foodsaver', [
            'id' => $this->user['id'],
            'sleep_status' => SleepStatus::TEMP
        ]);

        // no sleeping
        $I->login($this->user['email']);
        $I->sendPATCH('api/users/current/sleep-mode', ['mode' => SleepStatus::NONE]);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeInDatabase('fs_foodsaver', [
            'id' => $this->user['id'],
            'sleep_status' => SleepStatus::NONE
        ]);
    }

    public function cannotSetTemporarySleepStatusWithoutLimits(ApiTester $I): void
    {
        $I->updateInDatabase('fs_foodsaver', ['sleep_status' => SleepStatus::NONE], ['id' => $this->user['id']]);

        // without 'from'
        $I->login($this->user['email']);
        $I->sendPATCH('api/users/current/sleep-mode', [
            'mode' => SleepStatus::TEMP,
            'to' => Carbon::today()->addWeek()->format('d.m.Y')
        ]);
        $I->seeResponseCodeIs(HttpCode::BAD_REQUEST);
        $I->seeInDatabase('fs_foodsaver', [
            'id' => $this->user['id'],
            'sleep_status' => SleepStatus::NONE
        ]);

        // without 'to'
        $I->login($this->user['email']);
        $I->sendPATCH('api/users/current/sleep-mode', [
            'mode' => SleepStatus::TEMP,
            'from' => Carbon::today()->addDay()->format('d.m.Y'),
        ]);
        $I->seeResponseCodeIs(HttpCode::BAD_REQUEST);
        $I->seeInDatabase('fs_foodsaver', [
            'id' => $this->user['id'],
            'sleep_status' => SleepStatus::NONE
        ]);
    }

    public function cannotUseInvalidSleepStatusDates(ApiTester $I): void
    {
        $I->updateInDatabase('fs_foodsaver', ['sleep_status' => SleepStatus::NONE], ['id' => $this->user['id']]);

        $I->login($this->user['email']);
        $I->sendPATCH('api/users/current/sleep-mode', [
            'mode' => SleepStatus::TEMP,
            'from' => 'abcdefg',
            'to' => Carbon::today()->addWeek()->format('d.m.Y')
        ]);
        $I->seeResponseCodeIs(HttpCode::UNPROCESSABLE_ENTITY);
        $I->seeInDatabase('fs_foodsaver', [
            'id' => $this->user['id'],
            'sleep_status' => SleepStatus::NONE
        ]);
    }

    /**
     * Test invalid status values with their expected HTTP response codes.
     *
     * @example{"mode": null, "expectedCode": "UNPROCESSABLE_ENTITY"}
     * @example{"mode": "abc", "expectedCode": "UNPROCESSABLE_ENTITY"}
     * @example{"mode": "", "expectedCode": "UNPROCESSABLE_ENTITY"}
     * @example{"mode": -1, "expectedCode": "BAD_REQUEST"}
     * @example{"mode": 5, "expectedCode": "BAD_REQUEST"}
     * @example{"mode": 100, "expectedCode": "BAD_REQUEST"}
     */
    public function cannotUseInvalidStatus(ApiTester $I, Example $example): void
    {
        $I->updateInDatabase('fs_foodsaver', ['sleep_status' => SleepStatus::NONE], ['id' => $this->user['id']]);

        $I->login($this->user['email']);
        $I->sendPATCH('api/users/current/sleep-mode', ['mode' => $example['mode']]);
        $I->seeResponseCodeIs(constant('Codeception\Util\HttpCode::' . $example['expectedCode']));
        $I->seeInDatabase('fs_foodsaver', [
            'id' => $this->user['id'],
            'sleep_status' => SleepStatus::NONE
        ]);
    }

    // Test if sleeping status includes the until date
    public function isSleepingIncludesUntilDate(ApiTester $I): void
    {
        $today = Carbon::today();
        $yesterday = $today->copy()->subDay();
        $tomorrow = $today->copy()->addDay();
        $user = $I->createFoodsaver();
        $I->login($user['email']);

        // Check on the day before sleep_until
        $I->sendPATCH('api/users/current/sleep-mode', [
            'mode' => SleepStatus::TEMP,
            'from' => $today->format('Y-m-d'),
            'to' => $tomorrow->format('Y-m-d'),
        ]);
        $I->sendGET('/api/users/' . $user['id']);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseContainsJson(['isSleeping' => true]);

        // Check on the same day as sleep_until
        $I->sendPATCH('api/users/current/sleep-mode', [
            'mode' => SleepStatus::TEMP,
            'from' => $yesterday->format('Y-m-d'),
            'to' => $today->format('Y-m-d'),
        ]);
        $I->sendGET('/api/users/' . $user['id']);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseContainsJson(['isSleeping' => true]);

        // Check on one-day sleeping
        $I->sendPATCH('api/users/current/sleep-mode', [
            'mode' => SleepStatus::TEMP,
            'from' => $today->format('Y-m-d'),
            'to' => $today->format('Y-m-d'),
        ]);
        $I->sendGET('/api/users/' . $user['id']);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseContainsJson(['isSleeping' => true]);

        // Check on the day after sleep_until
        $I->sendPATCH('api/users/current/sleep-mode', [
            'mode' => SleepStatus::TEMP,
            'from' => $yesterday->format('Y-m-d'),
            'to' => $yesterday->format('Y-m-d'),
        ]);
        $I->sendGET('/api/users/' . $user['id']);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseContainsJson(['isSleeping' => false]);
    }

    /**
     * @example{ "loginUser": 0, "testUser": 0, "isOnTeamPage": false, "allowChange": false }
     * @example{ "loginUser": 0, "testUser": 0, "isOnTeamPage": true, "allowChange": true }
     * @example{ "loginUser": 1, "testUser": 0, "isOnTeamPage": false, "allowChange": false }
     * @example{ "loginUser": 1, "testUser": 0, "isOnTeamPage": true, "allowChange": false }
     * @example{ "loginUser": 1, "testUser": 1, "isOnTeamPage": false, "allowChange": false }
     * @example{ "loginUser": 1, "testUser": 1, "isOnTeamPage": true, "allowChange": true }
     * @example{ "loginUser": 2, "testUser": 0, "isOnTeamPage": false, "allowChange": false }
     * @example{ "loginUser": 2, "testUser": 0, "isOnTeamPage": true, "allowChange": true }
     * @example{ "loginUser": 2, "testUser": 2, "isOnTeamPage": false, "allowChange": false }
     * @example{ "loginUser": 2, "testUser": 2, "isOnTeamPage": true, "allowChange": true }
     */
    public function changePositionAndAboutMePublic(ApiTester $I, Example $example): void
    {
        // loginUser[0] = foodsaver, loginUser[1] = ambassador, loginUser[2] = userOrga
        $loginUsers = [$this->user, $this->userAmbassador, $this->userOrga];
        $loginUser = $loginUsers[$example['loginUser']];

        $testUsers = [$this->user, $this->userAmbassador, $this->userOrga];
        $testUser = $testUsers[$example['testUser']];

        if ($example['isOnTeamPage']) {
            $I->addRegionMember(RegionIDs::TEAM_BOARD_MEMBER, $testUser['id']);
        }

        $positionDataOld = $I->grabFromDatabase('fs_foodsaver', 'position', ['id' => $testUser['id']]);
        $aboutMePublicDataOld = $I->grabFromDatabase('fs_foodsaver', 'position', ['id' => $testUser['id']]);
        $positionData = 'positionTestData';
        $aboutMePublicData = 'aboutMePublicTestData';

        $I->login($loginUser['email']);
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPatch('api/users/' . $testUser['id'] . '/profile', [
            'position' => $positionData,
            'aboutMePublic' => $aboutMePublicData
        ]);
        $I->seeResponseCodeIs(HttpCode::OK);

        if ($example['allowChange']) {
            // make sure that the values changed
            $I->seeInDatabase('fs_foodsaver', [
                'id' => $testUser['id'],
                'position' => $positionData,
                'about_me_public' => $aboutMePublicData
            ]);
        } else {
            // make sure that the values did not change
            $I->seeInDatabase('fs_foodsaver', [
                'id' => $testUser['id'],
                'position' => $positionDataOld,
                'about_me_public' => $aboutMePublicDataOld
            ]);
        }
    }

    /**
     * @example{ "loginUser": 0, "testUser": 0, "allowChange": true }
     * @example{ "loginUser": 0, "testUser": 1, "allowChange": false }
     * @example{ "loginUser": 0, "testUser": 2, "allowChange": false }
     * @example{ "loginUser": 0, "testUser": 3, "allowChange": false }
     * @example{ "loginUser": 1, "testUser": 0, "allowChange": true }
     * @example{ "loginUser": 1, "testUser": 1, "allowChange": true }
     * @example{ "loginUser": 1, "testUser": 2, "allowChange": false }
     * @example{ "loginUser": 1, "testUser": 3, "allowChange": false }
     * @example{ "loginUser": 2, "testUser": 0, "allowChange": false }
     * @example{ "loginUser": 2, "testUser": 1, "allowChange": false }
     * @example{ "loginUser": 2, "testUser": 2, "allowChange": true }
     * @example{ "loginUser": 2, "testUser": 3, "allowChange": false }
     * @example{ "loginUser": 3, "testUser": 0, "allowChange": true }
     * @example{ "loginUser": 3, "testUser": 1, "allowChange": true }
     * @example{ "loginUser": 3, "testUser": 2, "allowChange": true }
     * @example{ "loginUser": 3, "testUser": 3, "allowChange": true }
     */
    public function changeProfileData(ApiTester $I, Example $example): void
    {
        // loginUser[0] = foodsaver, loginUser[1] = ambassador, loginUser[2] = ambassador without a region, loginUser[3] = userOrga
        $users = [$this->user, $this->userAmbassador, $this->userAmbassadorWithoutRegion, $this->userOrga];
        $loginUser = $users[$example['loginUser']];
        $testUser = $users[$example['testUser']];

        $phoneOld = $I->grabFromDatabase('fs_foodsaver', 'telefon', ['id' => $testUser['id']]);
        $phoneData = '123456789';

        $I->login($loginUser['email']);
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPatch('api/users/' . $testUser['id'] . '/profile', [
            'phone' => $phoneData,
        ]);
        $I->seeResponseCodeIs(HttpCode::OK);

        if ($example['allowChange']) {
            // make sure that the values changed
            $I->seeInDatabase('fs_foodsaver', [
                'telefon' => $phoneData,
                'id' => $testUser['id'],
            ]);
        } else {
            // make sure that the values did not change
            $I->seeInDatabase('fs_foodsaver', [
                'telefon' => $phoneOld,
                'id' => $testUser['id'],
            ]);
        }
    }

    /**
     * @example{ "loginUser": 0, "testUser": 0, "allowChange": true }
     * @example{ "loginUser": 0, "testUser": 1, "allowChange": false }
     * @example{ "loginUser": 0, "testUser": 2, "allowChange": false }
     * @example{ "loginUser": 1, "testUser": 0, "allowChange": false }
     * @example{ "loginUser": 1, "testUser": 1, "allowChange": true }
     * @example{ "loginUser": 1, "testUser": 2, "allowChange": false }
     * @example{ "loginUser": 2, "testUser": 0, "allowChange": true }
     * @example{ "loginUser": 2, "testUser": 1, "allowChange": true }
     * @example{ "loginUser": 2, "testUser": 2, "allowChange": true }
     */
    public function changeAboutMeInternal(ApiTester $I, Example $example): void
    {
        // loginUser[0] = foodsaver, loginUser[1] = ambassador, loginUser[2] = userOrga
        $users = [$this->user, $this->userAmbassador, $this->userOrga];
        $loginUser = $users[$example['loginUser']];
        $testUser = $users[$example['testUser']];

        $aboutMeOld = $I->grabFromDatabase('fs_foodsaver', 'about_me_intern', ['id' => $testUser['id']]);
        $aboutMe = 'qiweqüiwneqowneqwiebqiwe';

        $I->login($loginUser['email']);
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPatch('api/users/' . $testUser['id'] . '/profile', [
            'aboutMeInternal' => $aboutMe,
        ]);
        $I->seeResponseCodeIs(HttpCode::OK);

        if ($example['allowChange']) {
            // make sure that the values changed
            $I->seeInDatabase('fs_foodsaver', ['about_me_intern' => $aboutMe, 'id' => $testUser['id']]);
        } else {
            // make sure that the values did not change
            $I->seeInDatabase('fs_foodsaver', ['about_me_intern' => $aboutMeOld, 'id' => $testUser['id']]);
        }
    }

    /**
     * Region IDs that don't exist or that have a type which is not allowed for home regions should return 400. A null
     * value for the region id should return a 200 response but not change the database entry.
     *
     * @example{ "regionId": null, "result": true, "changed": false }
     * @example{ "regionId": 99999, "result": false, "changed": false }
     * @example{ "regionId": "region2", "result": true, "changed": true }
     * @example{ "regionId": "regionState", "result": false, "changed": false }
     */
    public function canOnlySetValidHomeRegion(ApiTester $I, Example $example): void
    {
        $oldRegionId = $I->grabFromDatabase('fs_foodsaver', 'bezirk_id', ['id' => $this->user['id']]);
        $regionId = is_string($example['regionId']) ? $this->{$example['regionId']}['id'] : $example['regionId'];

        $I->login($this->userOrga['email']);
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPatch('api/users/' . $this->user['id'] . '/profile', [
            'regionId' => $regionId
        ]);

        if ($example['result']) {
            $I->seeResponseCodeIs(HttpCode::OK);
        } else {
            $I->seeResponseCodeIs(HttpCode::BAD_REQUEST);
        }

        if ($example['changed']) {
            // make sure that the values changed
            $I->seeInDatabase('fs_foodsaver', ['bezirk_id' => $regionId, 'id' => $this->user['id']]);
        } else {
            // make sure that the values did not change
            $I->seeInDatabase('fs_foodsaver', ['bezirk_id' => $oldRegionId, 'id' => $this->user['id']]);
        }
    }

    public function canNotChangePasswordWithoutLogin(ApiTester $I): void
    {
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPatch('api/users/current/password', [
            'oldPassword' => $this->passwordOfUser,
            'newPassword' => $this->faker->password(8)
        ]);
        $I->seeResponseCodeIs(HttpCode::UNAUTHORIZED);
    }

    public function canNotChangePasswordWithoutOldPassword(ApiTester $I): void
    {
        $I->login($this->userWithPassword['email'], $this->passwordOfUser);
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPatch('api/users/current/password', [
            'oldPassword' => 'abcdefghi',
            'newPassword' => $this->faker->password(8)
        ]);
        $I->seeResponseCodeIs(HttpCode::FORBIDDEN);
    }

    public function canNotChangePasswordIfTooShort(ApiTester $I): void
    {
        $I->login($this->userWithPassword['email'], $this->passwordOfUser);
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPatch('api/users/current/password', [
            'oldPassword' => $this->passwordOfUser,
            'newPassword' => $this->faker->password(2, 7)
        ]);
        $I->seeResponseCodeIs(HttpCode::BAD_REQUEST);
    }

    public function canChangeValidPassword(ApiTester $I): void
    {
        $newPassword = $this->faker->password(8) . 'aA1'; // Ensure minimum length and complexity requirements

        $I->login($this->userWithPassword['email'], $this->passwordOfUser);
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPatch('api/users/current/password', [
            'oldPassword' => $this->passwordOfUser,
            'newPassword' => $newPassword
        ]);
        $I->seeResponseCodeIs(HttpCode::OK);

        $I->login($this->userWithPassword['email'], $newPassword);
        $I->seeResponseCodeIs(HttpCode::OK);
    }

    /**
     * @example{ "loginUser": "foodsaver", "testUser": "foodsaver", "canRead": true }
     * @example{ "loginUser": "foodsaver", "testUser": "ambassador", "canRead": false }
     * @example{ "loginUser": "foodsaver", "testUser": "ambassadorWithoutRegion", "canRead": false }
     * @example{ "loginUser": "foodsaver", "testUser": "orga", "canRead": false }
     * @example{ "loginUser": "ambassador", "testUser": "foodsaver", "canRead": true }
     * @example{ "loginUser": "ambassador", "testUser": "ambassador", "canRead": true }
     * @example{ "loginUser": "ambassador", "testUser": "ambassadorWithoutRegion", "canRead": false }
     * @example{ "loginUser": "ambassador", "testUser": "orga", "canRead": false }
     * @example{ "loginUser": "ambassadorWithoutRegion", "testUser": "foodsaver", "canRead": false }
     * @example{ "loginUser": "ambassadorWithoutRegion", "testUser": "ambassador", "canRead": false }
     * @example{ "loginUser": "ambassadorWithoutRegion", "testUser": "ambassadorWithoutRegion", "canRead": true }
     * @example{ "loginUser": "ambassadorWithoutRegion", "testUser": "orga", "canRead": false }
     * @example{ "loginUser": "orga", "testUser": "foodsaver", "canRead": true }
     * @example{ "loginUser": "orga", "testUser": "ambassador", "canRead": true }
     * @example{ "loginUser": "orga", "testUser": "ambassadorWithoutRegion", "canRead": true }
     * @example{ "loginUser": "orga", "testUser": "orga", "canRead": true }
     */
    public function readProfileData(ApiTester $I, Example $example): void
    {
        $users = (object)[
            'foodsaver' => $this->user,
            'ambassador' => $this->userAmbassador,
            'ambassadorWithoutRegion' => $this->userAmbassadorWithoutRegion,
            'orga' => $this->userOrga
        ];
        $loginUser = $users->{$example['loginUser']};
        $testUser = $users->{$example['testUser']};

        $I->login($loginUser['email']);
        $I->sendGET('api/users/' . $testUser['id'] . '/profile-settings');

        if ($example['canRead']) {
            $I->seeResponseCodeIs(HttpCode::OK);
            $I->seeResponseIsJson();

            // Verify response contains correct data from database
            $I->seeResponseContainsJson([
                'id' => (int)$testUser['id'],
                'firstName' => $I->grabFromDatabase('fs_foodsaver', 'name', ['id' => $testUser['id']]),
                'lastName' => $I->grabFromDatabase('fs_foodsaver', 'nachname', ['id' => $testUser['id']]),
                'role' => (int)$I->grabFromDatabase('fs_foodsaver', 'rolle', ['id' => $testUser['id']]),
                'position' => $I->grabFromDatabase('fs_foodsaver', 'position', ['id' => $testUser['id']]),
                'regionId' => (int)$I->grabFromDatabase('fs_foodsaver', 'bezirk_id', ['id' => $testUser['id']]),
                'gender' => (int)$I->grabFromDatabase('fs_foodsaver', 'geschlecht', ['id' => $testUser['id']]),
                'aboutMePublic' => $I->grabFromDatabase('fs_foodsaver', 'about_me_public', ['id' => $testUser['id']])
            ]);

            $photo = $I->grabFromDatabase('fs_foodsaver', 'photo', ['id' => $testUser['id']]);
            if ($photo) {
                $I->seeResponseContainsJson(['photo' => $photo]);
            }

            $mobile = $I->grabFromDatabase('fs_foodsaver', 'handy', ['id' => $testUser['id']]);
            $phone = $I->grabFromDatabase('fs_foodsaver', 'telefon', ['id' => $testUser['id']]);

            // At least one of mobile or phone must contain a phone number
            $I->assertTrue($mobile !== null || $phone !== null, 'At least one phone number (mobile or phone) must be set');

            if ($mobile) {
                $I->seeResponseContainsJson(['mobile' => $mobile]);
            }
            if ($phone) {
                $I->seeResponseContainsJson(['phone' => $phone]);
            }

            $birthday = $I->grabFromDatabase('fs_foodsaver', 'geb_datum', ['id' => $testUser['id']]);
            if ($birthday) {
                $I->seeResponseMatchesJsonType(['birthday' => 'string']);
            }

            $plz = $I->grabFromDatabase('fs_foodsaver', 'plz', ['id' => $testUser['id']]);
            $stadt = $I->grabFromDatabase('fs_foodsaver', 'stadt', ['id' => $testUser['id']]);
            $anschrift = $I->grabFromDatabase('fs_foodsaver', 'anschrift', ['id' => $testUser['id']]);
            if ($plz || $stadt || $anschrift) {
                $I->seeResponseMatchesJsonType(['address' => 'array']);
            }

            $lat = $I->grabFromDatabase('fs_foodsaver', 'lat', ['id' => $testUser['id']]);
            $lon = $I->grabFromDatabase('fs_foodsaver', 'lon', ['id' => $testUser['id']]);
            if ($lat && $lon) {
                $I->seeResponseMatchesJsonType(['coordinate' => 'array']);
            }

            // aboutMeInternal is only visible for certain users
            if ($example['loginUser'] === $example['testUser'] || $example['loginUser'] === 'orga') {
                $aboutMeInternal = $I->grabFromDatabase('fs_foodsaver', 'about_me_intern', ['id' => $testUser['id']]);
                if ($aboutMeInternal) {
                    $I->seeResponseContainsJson(['aboutMeInternal' => $aboutMeInternal]);
                }
            }
        } else {
            $I->seeResponseCodeIs(HttpCode::FORBIDDEN);
        }
    }

    /**
     * Users are allowed to request a change of their own email. This should trigger a confirmation email. Orga users
     * that are support admins are allowed to change the email address of a profile without triggering a confirmation
     * email.
     *
     * Users: 0=foodsaver, 1=ambassador, 2=orga, 3=orga and support group admin
     *
     * @example{ "loginUser": 0, "allowChange": true, "changeImmediately": false }
     * @example{ "loginUser": 1, "allowChange": false, "changeImmediately": false }
     * @example{ "loginUser": 2, "allowChange": false, "changeImmediately": false }
     * @example{ "loginUser": 3, "allowChange": true, "changeImmediately": true }
     */
    public function canRequestEmailChange(ApiTester $I, Example $example): void
    {
        if ($example['loginUser'] == 3) {
            // Create an orga user who is also admin of the support group
            $loginUser = $I->createOrga();
            $I->createWorkingGroup('Support', ['parent_id' => RegionIDs::GLOBAL_WORKING_GROUPS, 'id' => RegionIDs::IT_SUPPORT_GROUP]);
            $I->addRegionMember(RegionIDs::IT_SUPPORT_GROUP, $loginUser['id']);
            $I->addRegionAdmin(RegionIDs::IT_SUPPORT_GROUP, $loginUser['id']);
        } else {
            $users = [$this->user, $this->userAmbassador, $this->userOrga];
            $loginUser = $users[$example['loginUser']];
        }
        $newEmail = $this->faker->email();

        $I->login($loginUser['email']);
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPatch('api/users/' . $this->user['id'] . '/email', [
            'email' => $newEmail,
            'password' => 'password'
        ]);

        if ($example['allowChange']) {
            $I->seeResponseCodeIs(HttpCode::OK);
            if ($example['changeImmediately']) {
                $I->seeInDatabase('fs_foodsaver', ['id' => $this->user['id'], 'email' => $newEmail]);
            } else {
                $I->seeInDatabase('fs_mailchange', ['foodsaver_id' => $this->user['id'], 'newmail' => $newEmail]);
            }
        } else {
            $I->seeResponseCodeIs(HttpCode::FORBIDDEN);
            $I->dontSeeInDatabase('fs_mailchange', ['foodsaver_id' => $this->user['id'], 'newmail' => $newEmail]);
        }
    }
}
