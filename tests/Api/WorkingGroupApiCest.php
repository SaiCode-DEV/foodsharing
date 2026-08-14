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
    private array $globalWorkingGroup;
    private array $regionalWorkingGroup;
    private array $subWorkingGroup;
    private array $foodsharer;
    private array $user;
    private array $userAdmin;
    private array $userOrga;
    private array $testRegion;

    public function _before(ApiTester $I): void
    {
        $this->faker = Factory::create('de_DE');

        $this->testRegion = $I->createRegion('test region');
        $this->regionalWorkingGroup = $I->createWorkingGroup('test AG in test region', ['parent_id' => $this->testRegion['id'], 'apply_type' => ApplyType::EVERYBODY]);
        $this->globalWorkingGroup = $I->createWorkingGroup('test', ['parent_id' => RegionIDs::GLOBAL_WORKING_GROUPS, 'apply_type' => ApplyType::EVERYBODY]);
        $this->subWorkingGroup = $I->createWorkingGroup('test AG in test AG', ['parent_id' => $this->globalWorkingGroup['id'], 'apply_type' => ApplyType::EVERYBODY]);

        $this->user = $I->createFoodsaver();
        $this->userAdmin = $I->createFoodsaver();
        $this->userOrga = $I->createOrga();

        $I->addRegionMember($this->globalWorkingGroup['id'], $this->userAdmin['id']);
        $I->addRegionAdmin($this->globalWorkingGroup['id'], $this->userAdmin['id']);
        $I->addRegionMember($this->regionalWorkingGroup['id'], $this->userAdmin['id']);
        $I->addRegionAdmin($this->regionalWorkingGroup['id'], $this->userAdmin['id']);
        $I->addRegionMember($this->subWorkingGroup['id'], $this->userAdmin['id']);
        $I->addRegionAdmin($this->subWorkingGroup['id'], $this->userAdmin['id']);
    }

    /**
     * Tests I want:
     *
     * - Can Join global and regional open working group
     * - Can not join sub working group if not member of parent working group
     * - Can not join closed working group
     * - Can send mail to group as any user
     * - Can apply for global and regional working group only if group is apply=everybody and user is member of the region
     * - Can apply for sub working group only if user is member of the parent working group and group is apply=everybody
     */
    public function addingMembersToWorkingGroupsRequiresAdmin(ApiTester $I): void
    {
        $I->sendPOST('api/groups/' . $this->globalWorkingGroup['id'] . '/members/' . $this->user['id']);
        $I->seeResponseCodeIs(HttpCode::UNAUTHORIZED);

        $I->login($this->user['email']);
        $I->sendPOST('api/groups/' . $this->globalWorkingGroup['id'] . '/members/' . $this->user['id']);
        $I->seeResponseCodeIs(HttpCode::FORBIDDEN);

        $I->login($this->userAdmin['email']);
        $I->sendPOST('api/groups/' . $this->globalWorkingGroup['id'] . '/members/' . $this->user['id']);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeInDatabase('fs_foodsaver_has_bezirk', ['bezirk_id' => $this->globalWorkingGroup['id'], 'foodsaver_id' => $this->user['id']]);

        $I->login($this->user['email']);
        $I->sendPOST('api/groups/' . $this->globalWorkingGroup['id'] . '/members/' . $this->userOrga['id']);
        $I->seeResponseCodeIs(HttpCode::FORBIDDEN);

        $I->login($this->userAdmin['email']);
        $I->sendDelete('api/regions/' . $this->globalWorkingGroup['id'] . '/users/' . $this->user['id']);
        $I->seeResponseCodeIs(HttpCode::OK);
    }

    public function canNotAddMembersToInvalidWorkingGroups(ApiTester $I): void
    {
        $I->login($this->userAdmin['email']);
        $I->sendPOST('api/groups/' . $this->globalWorkingGroup['id'] + 11 . '/members/' . $this->user['id']);
        $I->seeResponseCodeIs(HttpCode::NOT_FOUND);
    }

    public function canNotAddDeletedUsersToWorkingGroups(ApiTester $I): void
    {
        // A soft-deleted account that still carries leftover rows (#2728)
        $deletedUser = $I->createFoodsaver(null, [
            'deleted_at' => date('Y-m-d H:i:s'),
        ]);

        $I->login($this->userAdmin['email']);
        $I->sendPOST('api/groups/' . $this->globalWorkingGroup['id'] . '/members/' . $deletedUser['id']);
        $I->seeResponseCodeIs(HttpCode::NOT_FOUND);
        $I->dontSeeInDatabase('fs_foodsaver_has_bezirk', ['bezirk_id' => $this->globalWorkingGroup['id'], 'foodsaver_id' => $deletedUser['id']]);
    }

    public function canOnlyJoinOpenWorkingGroup(ApiTester $I): void
    {
        $I->login($this->user['email']);
        $I->sendPOST('api/groups/' . $this->globalWorkingGroup['id'] . '/members/' . $this->user['id']);
        $I->seeResponseCodeIs(HttpCode::FORBIDDEN);

        $I->updateInDatabase('fs_bezirk', ['apply_type' => ApplyType::OPEN], ['id' => $this->globalWorkingGroup['id']]);

        $I->sendPOST('api/groups/' . $this->globalWorkingGroup['id'] . '/members/' . $this->user['id']);
        $I->seeResponseCodeIs(HttpCode::OK);
    }

    public function canNotEditWorkingGroupInvalidly(ApiTester $I): void
    {
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPatch('api/groups/' . $this->globalWorkingGroup['id'], $this->createFakeGroupData());
        $I->seeResponseCodeIs(HttpCode::UNAUTHORIZED);
        $I->seeInDatabase('fs_bezirk', ['id' => $this->globalWorkingGroup['id'], 'teaser' => $this->globalWorkingGroup['teaser']]);

        $I->login($this->user['email']);
        $I->addRegionMember($this->globalWorkingGroup['id'], $this->user['id']);
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPatch('api/groups/' . $this->globalWorkingGroup['id'], $this->createFakeGroupData());
        $I->seeResponseCodeIs(HttpCode::FORBIDDEN);
        $I->seeInDatabase('fs_bezirk', ['id' => $this->globalWorkingGroup['id'], 'teaser' => $this->globalWorkingGroup['teaser']]);

        // Can not edit not existing group
        $I->login($this->userOrga['email']);
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPatch('api/groups/' . $this->globalWorkingGroup['id'] + 11, $this->createFakeGroupData());
        $I->seeResponseCodeIs(HttpCode::NOT_FOUND);

        // Can not edit group with invalid data
        $newData = $this->createFakeGroupData();
        $newData['name'] = null;

        $I->login($this->userOrga['email']);
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPatch('api/groups/' . $this->globalWorkingGroup['id'], $newData);
        $I->seeResponseCodeIs(HttpCode::UNPROCESSABLE_ENTITY);
    }

    /**
     * @example["userAdmin"]
     * @example["userOrga"]
     */
    public function canEditWorkingGroup(ApiTester $I, Example $example): void
    {
        $newData = $this->createFakeGroupData();
        $I->login($this->{$example[0]}['email']);
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPatch('api/groups/' . $this->globalWorkingGroup['id'], $newData);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeInDatabase('fs_bezirk', array_merge(
            ['id' => $this->globalWorkingGroup['id']],
            $this->mapApiFormatToDatabase($newData)
        ));
    }

    public function sendMailToGroup(ApiTester $I): void
    {
        $I->deleteAllMails();

        $validMessage = '{ "message": "ThisIsATestMessage"}';
        $invalidMessage = '';

        // Test unauthorized
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPost('api/groups/' . $this->globalWorkingGroup['id'] . '/mail', $validMessage);
        $I->seeResponseCodeIs(HttpCode::UNAUTHORIZED);

        $I->login($this->user['email']);

        // Test Invalid Group
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPost('api/groups/' . $this->globalWorkingGroup['id'] + 100 . '/mail', $validMessage);
        $I->seeResponseCodeIs(HttpCode::NOT_FOUND);

        // Test invalid message
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPost('api/groups/' . $this->globalWorkingGroup['id'] . '/mail', $invalidMessage);
        $I->seeResponseCodeIs(HttpCode::BAD_REQUEST);

        // Send valid mail
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPost('api/groups/' . $this->globalWorkingGroup['id'] . '/mail', $validMessage);
        $I->seeResponseCodeIs(HttpCode::OK);

        $I->expectNumMails(1, 10);
        $mails = $I->getMails();
        $mail = $mails[0];

        $I->assertStringContainsString('ThisIsATestMessage', $mail->html);
        $I->assertStringContainsString($this->user['name'], $mail->subject);
        $I->assertContainsEquals($this->user['email'], array_map(fn ($value): string => $value->address, $mail->to));
        $I->assertContainsEquals('region-' . $this->globalWorkingGroup['id'] . '@foodsharing.network', array_map(fn ($value): string => $value->address, $mail->to));
        $I->assertContainsEquals($this->user['email'], array_map(fn ($value): string => $value->address, $mail->replyTo));
    }

    public function sendGroupRequest(ApiTester $I): void
    {
        $validRequest = '{ "application": "ThisIsATestMessage" }';
        $invalidMessage = '{ "motivation": "ThisIsATestMessage", "ability": "", "experience": "", "selectedTime": 1 }';

        // Test unauthorized
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPost('api/groups/' . $this->globalWorkingGroup['id'] . '/applications', $validRequest);
        $I->seeResponseCodeIs(HttpCode::UNAUTHORIZED);

        $I->login($this->user['email']);

        // Test wrong content
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPost('api/groups/' . $this->globalWorkingGroup['id'] . '/applications', $invalidMessage);
        $I->seeResponseCodeIs(HttpCode::UNPROCESSABLE_ENTITY);

        // Test Invalid Group
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPost('api/groups/' . $this->globalWorkingGroup['id'] + 100 . '/applications', $validRequest);
        $I->seeResponseCodeIs(HttpCode::NOT_FOUND);

        // Test send request
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPost('api/groups/' . $this->globalWorkingGroup['id'] . '/applications', $validRequest);
        $I->seeResponseCodeIs(HttpCode::OK);
    }

    public function canApplyToCorrectGroups(ApiTester $I): void
    {
        $I->login($this->user['email']);
        $validRequest = ['application' => 'ThisIsATestMessage'];
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPost('api/groups/' . $this->globalWorkingGroup['id'] . '/applications', $validRequest);
        $I->seeResponseCodeIs(HttpCode::OK);

        $I->sendPost('api/groups/' . $this->regionalWorkingGroup['id'] . '/applications', $validRequest);
        $I->seeResponseCodeIs(HttpCode::FORBIDDEN);

        $I->sendPost('api/groups/' . $this->subWorkingGroup['id'] . '/applications', $validRequest);
        $I->seeResponseCodeIs(HttpCode::FORBIDDEN);

        $I->login($this->userAdmin['email']);
        $I->sendPOST('api/groups/' . $this->globalWorkingGroup['id'] . '/members/' . $this->user['id']);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->addRegionMember($this->testRegion['id'], $this->user['id']);

        $I->login($this->user['email']);
        $I->sendPost('api/groups/' . $this->regionalWorkingGroup['id'] . '/applications', $validRequest);
        $I->seeResponseCodeIs(HttpCode::OK);

        $I->login($this->user['email']);
        $I->sendPost('api/groups/' . $this->subWorkingGroup['id'] . '/applications', $validRequest);
        $I->seeResponseCodeIs(HttpCode::OK);
    }

    public function usersDontGetAutoAddedToParentRegions(ApiTester $I): void
    {
        // Add user into working group where he is not in the parent group / region
        $I->login($this->userAdmin['email']);
        $I->sendPOST('api/groups/' . $this->subWorkingGroup['id'] . '/members/' . $this->user['id']);
        $I->seeResponseCodeIs(HttpCode::OK);

        $I->sendPOST('api/groups/' . $this->regionalWorkingGroup['id'] . '/members/' . $this->user['id']);
        $I->seeResponseCodeIs(HttpCode::OK);

        // Verify user is still NOT member of the global working group and test region
        $I->dontSeeInDatabase('fs_foodsaver_has_bezirk', ['foodsaver_id' => $this->user['id'], 'bezirk_id' => $this->globalWorkingGroup['id']]);
        $I->dontSeeInDatabase('fs_foodsaver_has_bezirk', ['foodsaver_id' => $this->user['id'], 'bezirk_id' => $this->testRegion['id']]);

        // User does get added to global working groups region though when added to a global working group
        $I->sendPOST('api/groups/' . $this->globalWorkingGroup['id'] . '/members/' . $this->user['id']);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeInDatabase('fs_foodsaver_has_bezirk', ['foodsaver_id' => $this->user['id'], 'bezirk_id' => RegionIDs::GLOBAL_WORKING_GROUPS]);
    }

    /**
     * Maps group data from the database to the format of the API.
     */
    public function groupListSeparatesMembershipFromAccess(ApiTester $I): void
    {
        // #2810: orga may access every group, which used to be shown as membership
        $closedGroup = $I->createWorkingGroup('closed group', [
            'parent_id' => RegionIDs::GLOBAL_WORKING_GROUPS,
            'apply_type' => ApplyType::NOBODY,
        ]);
        $I->addRegionMember($closedGroup['id'], $this->userAdmin['id']);

        $I->login($this->userOrga['email']);
        $I->sendGET('api/regions/' . RegionIDs::GLOBAL_WORKING_GROUPS . '/groups');
        $I->seeResponseCodeIs(HttpCode::OK);

        $groups = $I->grabDataFromResponseByJsonPath('$[?(@.id == ' . $closedGroup['id'] . ')]')[0];
        $I->assertTrue($groups['mayAccess'], 'orga may access every group');
        $I->assertFalse($groups['isMember'], 'but is not a member of it');

        // a group the orga really belongs to
        $I->addRegionMember($closedGroup['id'], $this->userOrga['id']);
        $I->sendGET('api/regions/' . RegionIDs::GLOBAL_WORKING_GROUPS . '/groups');
        $joined = $I->grabDataFromResponseByJsonPath('$[?(@.id == ' . $closedGroup['id'] . ')]')[0];
        $I->assertTrue($joined['isMember']);
    }

    private function mapApiFormatToDatabase(array $group): array
    {
        return [
            'name' => $group['name'],
            'teaser' => $group['description'],
            'photo' => $group['photo'] ?? '',
            'apply_type' => $group['applyType'],
        ];
    }

    private function createFakeGroupData(): array
    {
        return [
            'name' => $this->faker->name(),
            'description' => $this->faker->realText(),
            'photo' => null,
            'applyType' => [ApplyType::NOBODY, ApplyType::OPEN, ApplyType::EVERYBODY][random_int(0, 2)],
        ];
    }
}
