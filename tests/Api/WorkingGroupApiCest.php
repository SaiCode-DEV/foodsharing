<?php

declare(strict_types=1);

namespace Tests\Api;

use Codeception\Example;
use Codeception\Util\HttpCode;
use Faker\Factory;
use Faker\Generator;
use Foodsharing\Modules\Core\DBConstants\Region\ApplyType;
use Foodsharing\Modules\Core\DBConstants\Region\RegionIDs;
use Tests\Support\ApiTester;

/**
 * @group api-group-1
 */
class WorkingGroupApiCest
{
    private Generator $faker;
    private $workingGroup;
    private $workingGroup2;
    private $workingGroup3;
    private $foodsharer;
    private $user;
    private $userAdmin;
    private $userOrga;
    private $testRegion;

    public function _before(ApiTester $I): void
    {
        $this->faker = Factory::create('de_DE');

        $this->workingGroup = $I->createWorkingGroup('test', ['apply_type' => ApplyType::EVERYBODY]);
        $this->foodsharer = $I->createFoodsharer();
        $this->user = $I->createFoodsaver();
        $I->addRegionMember(RegionIDs::GLOBAL_WORKING_GROUPS, $this->user['id']);
        $this->userAdmin = $I->createFoodsaver();
        $I->addRegionMember(RegionIDs::GLOBAL_WORKING_GROUPS, $this->userAdmin['id']);
        $I->addRegionMember($this->workingGroup['id'], $this->userAdmin['id']);
        $I->addRegionAdmin($this->workingGroup['id'], $this->userAdmin['id']);
        $this->userOrga = $I->createOrga();

        $this->testRegion = $I->createRegion('test region');
        $this->workingGroup2 = $I->createWorkingGroup('test AG in test region', ['parent_id' => $this->testRegion['id'], 'apply_type' => ApplyType::EVERYBODY]);
        $I->addRegionAdmin($this->workingGroup2['id'], $this->userAdmin['id']);
        $this->workingGroup3 = $I->createWorkingGroup('test AG in test AG', ['parent_id' => $this->workingGroup['id'], 'apply_type' => ApplyType::EVERYBODY]);
        $I->addRegionAdmin($this->workingGroup3['id'], $this->userAdmin['id']);
    }

    public function canNotAddMembersToWorkingGroupsWithoutLogin(ApiTester $I): void
    {
        $I->sendPOST('api/groups/' . $this->workingGroup['id'] . '/members/' . $this->foodsharer['id']);
        $I->seeResponseCodeIs(HttpCode::UNAUTHORIZED);
    }

    public function canNooAddMembersToInvalidWorkingGroups(ApiTester $I): void
    {
        $I->login($this->userAdmin['email']);
        $I->sendPOST('api/groups/' . $this->workingGroup['id'] + 11 . '/members/' . $this->user['id']);
        $I->seeResponseCodeIs(HttpCode::NOT_FOUND);
    }

    public function canAddMembersToWorkingGroups(ApiTester $I): void
    {
        $I->login($this->userOrga['email']);
        $I->sendPOST('api/groups/' . $this->workingGroup['id'] . '/members/' . $this->user['id']);
        $I->seeResponseCodeIs(HttpCode::OK);
    }

    public function canRemoveMembersFromWorkingGroups(ApiTester $I): void
    {
        $I->login($this->userOrga['email']);
        $I->sendDelete('api/region/' . $this->workingGroup['id'] . '/members/' . $this->user['id']);
        $I->seeResponseCodeIs(HttpCode::OK);
    }

    public function canJoinOpenWorkingGroup(ApiTester $I): void
    {
        $workingGroupOpen = $I->createWorkingGroup('test open', ['apply_type' => ApplyType::OPEN]);

        $I->login($this->user['email']);
        $I->sendPOST('api/groups/' . $workingGroupOpen['id'] . '/members/' . $this->user['id']);
        $I->seeResponseCodeIs(HttpCode::OK);
    }

    public function canNotJoinClosedWorkingGroup(ApiTester $I): void
    {
        $I->login($this->user['email']);
        $I->sendPOST('api/groups/' . $this->workingGroup['id'] . '/members/' . $this->user['id']);
        $I->seeResponseCodeIs(HttpCode::FORBIDDEN);
    }

    public function canNotEditWorkingGroup(ApiTester $I): void
    {
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPatch('api/groups/' . $this->workingGroup['id'], $this->createFakeGroupData());
        $I->seeResponseCodeIs(HttpCode::UNAUTHORIZED);
        $I->seeInDatabase('fs_bezirk', ['id' => $this->workingGroup['id'], 'teaser' => $this->workingGroup['teaser']]);

        $I->login($this->user['email']);
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPatch('api/groups/' . $this->workingGroup['id'], $this->createFakeGroupData());
        $I->seeResponseCodeIs(HttpCode::FORBIDDEN);
        $I->seeInDatabase('fs_bezirk', ['id' => $this->workingGroup['id'], 'teaser' => $this->workingGroup['teaser']]);

        // Can not edit not existing group
        $newData = $this->createFakeGroupData();
        $I->login($this->userOrga['email']);
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPatch('api/groups/' . $this->workingGroup['id'] + 11, $newData);
        $I->seeResponseCodeIs(HttpCode::NOT_FOUND);

        // Can not edit group with invalid data
        $newData = $this->createFakeGroupData();
        $newData['name'] = null;

        $I->login($this->userOrga['email']);
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPatch('api/groups/' . $this->workingGroup['id'], $newData);
        $I->seeResponseCodeIs(HttpCode::UNPROCESSABLE_ENTITY);
    }

    /**
     * @example["userAdmin"]
     * @example["userOrga"]
     */
    public function canEditWorkingGroupAsOrgaAndAdmin(ApiTester $I, Example $example): void
    {
        $newData = $this->createFakeGroupData();
        $I->login($this->{$example[0]}['email']);
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPatch('api/groups/' . $this->workingGroup['id'], $newData);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeInDatabase('fs_bezirk', array_merge(
            ['id' => $this->workingGroup['id']],
            $this->mapApiFormatToDatabase($newData)
        ));
    }

    /**
     * @example["user"]
     * @example["userAdmin"]
     * @example["userOrga"]
     */
    public function sendMailToGroup(ApiTester $I, Example $example): void
    {
        $I->deleteAllMails();

        $validMessage = '{ "message": "ThisIsATestMessage"}';
        $invalidMessage = '';
        // Test unauthorized
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPost('api/groups/' . $this->workingGroup['id'] . '/mail', $invalidMessage);
        $I->seeResponseCodeIs(HttpCode::UNPROCESSABLE_ENTITY);

        // Test unauthorized
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPost('api/groups/null/mail', $invalidMessage);
        $I->seeResponseCodeIs(HttpCode::UNPROCESSABLE_ENTITY);

        // Test unauthorized
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPost('api/groups/' . $this->workingGroup['id'] . '/mail', $validMessage);
        $I->seeResponseCodeIs(HttpCode::UNAUTHORIZED);

        $I->login($this->{$example[0]}['email']);

        // Test Invalid Group
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPost('api/groups/' . $this->workingGroup['id'] + 100 . '/mail', $validMessage);
        $I->seeResponseCodeIs(HttpCode::NOT_FOUND);

        // Send valid mail
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPost('api/groups/' . $this->workingGroup['id'] . '/mail', $validMessage);
        $I->seeResponseCodeIs(HttpCode::OK);

        $I->expectNumMails(1, 10);
        $mails = $I->getMails();
        $mail = $mails[0];

        $I->assertStringContainsString('ThisIsATestMessage', $mail->html);
        $I->assertStringContainsString($this->{$example[0]}['name'], $mail->subject);
        $I->assertContainsEquals($this->{$example[0]}['email'], array_map(fn ($value): string => $value->address, $mail->to));
        $I->assertContainsEquals('region-' . $this->workingGroup['id'] . '@foodsharing.network', array_map(fn ($value): string => $value->address, $mail->to));
        $I->assertContainsEquals($this->{$example[0]}['email'], array_map(fn ($value): string => $value->address, $mail->replyTo));
    }

    public function sendGroupRequest(ApiTester $I): void
    {
        $validRequest = '{ "motivation": "ThisIsATestMessage", "ability": "", "experience": "", "selectedTime": 1}';
        $invalidMessage = '{ "motivation": "ThisIsATestMessage", "ability": "", "experience": "", "selectedTime": "a"}';
        // Test unauthorized
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPost('api/groups/' . $this->workingGroup['id'] . '/applications', $validRequest);
        $I->seeResponseCodeIs(HttpCode::UNAUTHORIZED);

        $I->login($this->user['email']);

        // Test wrong content
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPost('api/groups/' . $this->workingGroup['id'] . '/applications', $invalidMessage);
        $I->seeResponseCodeIs(HttpCode::UNPROCESSABLE_ENTITY);

        // Test wrong content
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPost('api/groups/null/applications', $invalidMessage);
        $I->seeResponseCodeIs(HttpCode::UNPROCESSABLE_ENTITY);

        // Test Invalid Group
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPost('api/groups/' . $this->workingGroup['id'] + 100 . '/applications', $validRequest);
        $I->seeResponseCodeIs(HttpCode::NOT_FOUND);

        // Test send request
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPost('api/groups/' . $this->workingGroup['id'] . '/applications', $validRequest);
        $I->seeResponseCodeIs(HttpCode::OK);
    }

    public function canApplyForWorkingGroupInRegion(ApiTester $I): void
    {
        // We want users to be added to the parenting regions if this is NOT a
        // working groups. Otherwise, they'd later be added over night
        // automatically but won't have access until then
        $I->login($this->user['email']);

        // Verify user is not yet member of the test region
        $I->sendGET('api/user/current/regions');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->dontSeeResponseContainsJson(['id' => $this->testRegion['id']]);

        // Apply for working group that has the test region as parent
        $I->haveHttpHeader('Content-Type', 'application/json');
        $validRequest = ['motivation' => 'ThisIsATestMessage', 'ability' => '', 'experience' => '', 'selectedTime' => 1];
        $I->sendPost('api/groups/' . $this->workingGroup2['id'] . '/applications', $validRequest);
        $I->seeResponseCodeIs(HttpCode::OK);

        // Verify user is still NOT member of the test region
        $I->sendGET('api/user/current/regions');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->dontSeeResponseContainsJson(['id' => $this->testRegion['id']]);

        // Accept user into working group
        $I->login($this->userAdmin['email']);
        $I->sendPOST('api/groups/' . $this->workingGroup2['id'] . '/members/' . $this->user['id']);
        $I->seeResponseCodeIs(HttpCode::OK);

        // Verify user is now member of the test region
        $I->login($this->user['email']);
        $I->sendGET('api/user/current/regions');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseContainsJson(['id' => $this->testRegion['id']]);
    }

    public function canApplyForWorkingGroupInWorkingGroup(ApiTester $I): void
    {
        // We don't want users to be added to the parenting regions if this IS a
        // working group. Users will still need to apply separately for the
        // underlying working group if they want to become member here (real
        // life example: AG Betriebsketten and its children)
        $I->login($this->user['email']);

        // Verify user is NOT member of the test working group
        $I->sendGET('api/user/current/regions');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->dontSeeResponseContainsJson(['id' => $this->workingGroup['id']]);

        // Apply for working group that has the test working group as parent
        $I->haveHttpHeader('Content-Type', 'application/json');
        $validRequest = ['motivation' => 'ThisIsATestMessage', 'ability' => '', 'experience' => '', 'selectedTime' => 1];
        $I->sendPost('api/groups/' . $this->workingGroup3['id'] . '/applications', $validRequest);
        $I->seeResponseCodeIs(HttpCode::OK);

        // Verify user is still NOT member of the test working group
        $I->sendGET('api/user/current/regions');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->dontSeeResponseContainsJson(['id' => $this->workingGroup['id']]);

        // Accept user into working group
        $I->login($this->userAdmin['email']);
        $I->sendPOST('api/groups/' . $this->workingGroup['id'] . '/members/' . $this->user['id']);
        $I->seeResponseCodeIs(HttpCode::OK);

        // Verify user is still NOT member of the test region
        $I->login($this->user['email']);
        $I->sendGET('api/user/current/regions');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->dontSeeResponseContainsJson(['id' => $this->testRegion['id']]);
    }

    /**
     * Maps group data from the database to the format of the API.
     */
    private function mapApiFormatToDatabase(array $group): array
    {
        return [
            'name' => $group['name'],
            'teaser' => $group['description'],
            'photo' => $group['photo'] ?? '',
            'apply_type' => $group['applyType'],
            'banana_count' => $group['requiredBananas'],
            'fetch_count' => $group['requiredPickups'],
            'week_num' => $group['requiredWeeks'],
        ];
    }

    private function createFakeGroupData(): array
    {
        return [
            'name' => $this->faker->name(),
            'description' => $this->faker->realText(),
            'photo' => null,
            'applyType' => random_int(ApplyType::NOBODY, ApplyType::OPEN),
            'requiredBananas' => random_int(0, 10),
            'requiredPickups' => random_int(0, 100),
            'requiredWeeks' => random_int(0, 52),
        ];
    }
}
