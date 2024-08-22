<?php

declare(strict_types=1);

namespace Tests\Api;

use Carbon\Carbon;
use Codeception\Example;
use Codeception\Util\HttpCode;
use Foodsharing\Modules\Core\DBConstants\Foodsaver\SleepStatus;
use Foodsharing\Modules\Core\DBConstants\Region\RegionIDs;
use Foodsharing\Modules\Core\DBConstants\Unit\UnitType;
use Tests\Support\ApiTester;

class SettingsApiCest
{
    private $user;
    private $userAmbassador;
    private $userAmbassadorWithoutRegion;
    private $userOrga;
    private $region1;
    private $region2;
    private $regionState;

    public function _before(ApiTester $I): void
    {
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
    }

    public function canOnlySetSleepStatusWhenLoggedIn(ApiTester $I): void
    {
        $I->sendPATCH('api/user/sleepmode', ['mode' => SleepStatus::NONE]);
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
        $I->sendPATCH('api/user/sleepmode', ['mode' => SleepStatus::FULL]);
        $I->seeResponseCodeIs(HttpCode::NO_CONTENT);
        $I->seeInDatabase('fs_foodsaver', [
            'id' => $this->user['id'],
            'sleep_status' => SleepStatus::FULL
        ]);

        // temporary sleep mode
        $I->login($this->user['email']);
        $I->sendPATCH('api/user/sleepmode', [
            'mode' => SleepStatus::TEMP,
            'from' => Carbon::today()->addDay()->format('Y-m-d'),
            'to' => Carbon::today()->addWeek()->format('Y-m-d')
        ]);
        $I->seeResponseCodeIs(HttpCode::NO_CONTENT);
        $I->seeInDatabase('fs_foodsaver', [
            'id' => $this->user['id'],
            'sleep_status' => SleepStatus::TEMP
        ]);

        // no sleeping
        $I->login($this->user['email']);
        $I->sendPATCH('api/user/sleepmode', ['mode' => SleepStatus::NONE]);
        $I->seeResponseCodeIs(HttpCode::NO_CONTENT);
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
        $I->sendPATCH('api/user/sleepmode', [
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
        $I->sendPATCH('api/user/sleepmode', [
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
        $I->sendPATCH('api/user/sleepmode', [
            'mode' => SleepStatus::TEMP,
            'from' => 'abcdefg',
            'to' => Carbon::today()->addWeek()->format('d.m.Y')
        ]);
        $I->seeResponseCodeIs(HttpCode::BAD_REQUEST);
        $I->seeInDatabase('fs_foodsaver', [
            'id' => $this->user['id'],
            'sleep_status' => SleepStatus::NONE
        ]);
    }

    /**
     * @example [-1]
     * @example [5]
     * @example [100]
     * @example [null]
     * @example ["abc"]
     * @example [""]
     */
    public function cannotUseInvalidStatus(ApiTester $I, Example $example): void
    {
        $I->updateInDatabase('fs_foodsaver', ['sleep_status' => SleepStatus::NONE], ['id' => $this->user['id']]);

        $I->login($this->user['email']);
        $I->sendPATCH('api/user/sleepmode', ['mode' => $example[0]]);
        $I->seeResponseCodeIs(HttpCode::BAD_REQUEST);
        $I->seeInDatabase('fs_foodsaver', [
            'id' => $this->user['id'],
            'sleep_status' => SleepStatus::NONE
        ]);
    }

    /**
     * @example {"loginUser": 0, "testUser": 0, "isOnTeamPage": false, "allowChange": false}
     * @example {"loginUser": 0, "testUser": 0, "isOnTeamPage": true, "allowChange": true}
     * @example {"loginUser": 1, "testUser": 0, "isOnTeamPage": false, "allowChange": false}
     * @example {"loginUser": 1, "testUser": 0, "isOnTeamPage": true, "allowChange": false}
     * @example {"loginUser": 1, "testUser": 1, "isOnTeamPage": false, "allowChange": false}
     * @example {"loginUser": 1, "testUser": 1, "isOnTeamPage": true, "allowChange": true}
     * @example {"loginUser": 2, "testUser": 0, "isOnTeamPage": false, "allowChange": false}
     * @example {"loginUser": 2, "testUser": 0, "isOnTeamPage": true, "allowChange": true}
     * @example {"loginUser": 2, "testUser": 2, "isOnTeamPage": false, "allowChange": false}
     * @example {"loginUser": 2, "testUser": 2, "isOnTeamPage": true, "allowChange": true}
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
        $I->sendPatch('api/user/' . $testUser['id'] . '/profile', [
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
     * @example {"loginUser": 0, "testUser": 0, "allowChange": true}
     * @example {"loginUser": 0, "testUser": 1, "allowChange": false}
     * @example {"loginUser": 0, "testUser": 2, "allowChange": false}
     * @example {"loginUser": 0, "testUser": 3, "allowChange": false}
     * @example {"loginUser": 1, "testUser": 0, "allowChange": true}
     * @example {"loginUser": 1, "testUser": 1, "allowChange": true}
     * @example {"loginUser": 1, "testUser": 2, "allowChange": false}
     * @example {"loginUser": 1, "testUser": 3, "allowChange": false}
     * @example {"loginUser": 2, "testUser": 0, "allowChange": false}
     * @example {"loginUser": 2, "testUser": 1, "allowChange": false}
     * @example {"loginUser": 2, "testUser": 2, "allowChange": true}
     * @example {"loginUser": 2, "testUser": 3, "allowChange": false}
     * @example {"loginUser": 3, "testUser": 0, "allowChange": true}
     * @example {"loginUser": 3, "testUser": 1, "allowChange": true}
     * @example {"loginUser": 3, "testUser": 2, "allowChange": true}
     * @example {"loginUser": 3, "testUser": 3, "allowChange": true}
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
        $I->sendPatch('api/user/' . $testUser['id'] . '/profile', [
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
     * @example {"loginUser": 0, "testUser": 0, "allowChange": true}
     * @example {"loginUser": 0, "testUser": 1, "allowChange": false}
     * @example {"loginUser": 0, "testUser": 2, "allowChange": false}
     * @example {"loginUser": 1, "testUser": 0, "allowChange": false}
     * @example {"loginUser": 1, "testUser": 1, "allowChange": true}
     * @example {"loginUser": 1, "testUser": 2, "allowChange": false}
     * @example {"loginUser": 2, "testUser": 0, "allowChange": true}
     * @example {"loginUser": 2, "testUser": 1, "allowChange": true}
     * @example {"loginUser": 2, "testUser": 2, "allowChange": true}
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
        $I->sendPatch('api/user/' . $testUser['id'] . '/profile', [
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
     * @example {"regionId": null, "result": true, "changed": false}
     * @example {"regionId": 99999, "result": false, "changed": false}
     * @example {"regionId": "region2", "result": true, "changed": true}
     * @example {"regionId": "regionState", "result": false, "changed": false}
     */
    public function canOnlySetValidHomeRegion(ApiTester $I, Example $example): void
    {
        $oldRegionId = $I->grabFromDatabase('fs_foodsaver', 'bezirk_id', ['id' => $this->user['id']]);
        $regionId = is_string($example['regionId']) ? $this->{$example['regionId']}['id'] : $example['regionId'];

        $I->login($this->userOrga['email']);
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPatch('api/user/' . $this->user['id'] . '/profile', [
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
}
