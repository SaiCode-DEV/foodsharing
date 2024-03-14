<?php

declare(strict_types=1);

namespace Tests\Api;

use Codeception\Example;
use Codeception\Util\HttpCode;
use Faker\Factory;
use Faker\Generator;
use Foodsharing\Modules\Core\DBConstants\Region\ApplyType;
use Tests\Support\ApiTester;

class WorkingGroupApiCest
{
    private Generator $faker;
    private $workingGroup;
    private $workingGroupOpen;
    private $user;
    private $userAdmin;
    private $userOrga;

    public function _before(ApiTester $I): void
    {
        $this->faker = Factory::create('de_DE');

        $this->workingGroup = $I->createWorkingGroup('test', ['apply_type' => ApplyType::EVERYBODY]);
        $this->workingGroupOpen = $I->createWorkingGroup('test open', ['apply_type' => ApplyType::OPEN]);
        $this->user = $I->createFoodsaver();
        $this->userAdmin = $I->createFoodsaver();
        $I->addRegionMember($this->workingGroup['id'], $this->userAdmin['id']);
        $I->addRegionAdmin($this->workingGroup['id'], $this->userAdmin['id']);
        $this->userOrga = $I->createOrga();
    }

    public function canAddMembersToWorkingGroups(ApiTester $I): void
    {
        $I->login($this->userOrga['email']);
        $I->sendPOST('api/groups/' . $this->workingGroup['id'] . '/members/' . $this->user['id']);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseIsJson();
    }

    public function canRemoveMembersFromWorkingGroups(ApiTester $I): void
    {
        $I->login($this->userOrga['email']);
        $I->sendDelete('api/region/' . $this->workingGroup['id'] . '/members/' . $this->user['id']);
        $I->seeResponseCodeIs(HttpCode::OK);
    }

    public function canJoinOpenWorkingGroup(ApiTester $I): void
    {
        $I->login($this->user['email']);
        $I->sendPOST('api/groups/' . $this->workingGroupOpen['id'] . '/members/' . $this->user['id']);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseIsJson();
    }

    public function canNotJoinClosedWorkingGroup(ApiTester $I): void
    {
        $I->login($this->user['email']);
        $I->sendPOST('api/groups/' . $this->workingGroup['id'] . '/members/' . $this->user['id']);
        $I->seeResponseCodeIs(HttpCode::FORBIDDEN);
        $I->seeResponseIsJson();
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
