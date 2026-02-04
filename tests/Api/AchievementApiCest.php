<?php

declare(strict_types=1);

namespace Tests\Api;

use Codeception\Util\HttpCode;
use Foodsharing\Modules\Achievement\DTO\Achievement;
use Foodsharing\Modules\Core\DBConstants\Achievement\DuplicateMode;
use Foodsharing\Modules\Core\DBConstants\Achievement\VisibilityType;
use Foodsharing\Modules\Core\DBConstants\Region\RegionIDs;
use Symfony\Component\HttpFoundation\Response;
use Tests\Support\ApiTester;

/**
 * @group api-group-1
 */
class AchievementApiCest
{
    protected ApiTester $tester;
    private Achievement $achievement;
    private Achievement $otherAchievement;
    private array $admin;
    private array $user;
    private $region;

    public function _before(ApiTester $I): void
    {
        $this->tester = $I;

        $this->user = $this->tester->createFoodsaver();
        $this->admin = $this->tester->createAmbassador();

        $this->region = $this->tester->createRegion('region', ['parent_id' => 0], fillMailbox: false);
        $createWGGroup = $this->tester->createRegion('region', ['id' => RegionIDs::CREATING_WORK_GROUPS_WORK_GROUP], fillMailbox: false);
        $this->tester->addRegionMember($this->region['id'], $this->user['id']);
        $this->tester->addRegionMember($this->region['id'], $this->admin['id']);
        $this->tester->addRegionAdmin($this->region['id'], $this->admin['id']);
        $this->tester->addRegionMember($createWGGroup['id'], $this->admin['id']);
        $this->tester->addRegionAdmin($createWGGroup['id'], $this->admin['id']);

        $this->achievement = Achievement::create(1, $this->region['id'], 'Some name', 'Some description', 'icon', 365, null, null, VisibilityType::GLOBAL, DuplicateMode::OVERRIDE);

        $this->otherAchievement = clone $this->achievement;
        $this->otherAchievement->id = 2;
        $this->otherAchievement->regionId = 0;

        $this->tester->addAchievement($this->achievementToArray($this->achievement));
        $this->tester->addAchievement($this->achievementToArray($this->otherAchievement));
    }

    private function achievementToArray(Achievement $achievement): array
    {
        return [
            'id' => $achievement->id,
            'name' => $achievement->name,
            'region_id' => $achievement->regionId,
            'description' => $achievement->description,
            'icon' => $achievement->icon,
            'validity_in_days_after_assignment' => $achievement->validityInDaysAfterAssignment,
            'visibility_type' => $achievement->visibilityType->value,
            'duplicate_mode' => $achievement->duplicateMode->value,
        ];
    }

    private function achievementToArrayForApi(Achievement $achievement): array
    {
        return [
            'name' => $achievement->name,
            'regionId' => $achievement->regionId,
            'description' => $achievement->description,
            'icon' => $achievement->icon,
            'validityInDaysAfterAssignment' => $achievement->validityInDaysAfterAssignment,
            'visibilityType' => $achievement->visibilityType->value,
            'duplicateMode' => $achievement->duplicateMode->value,
        ];
    }

    public function getAchievementsFromRegion(ApiTester $I): void
    {
        $I->sendGET('api/achievements/region/' . $this->region['id']);
        $I->seeResponseCodeIs(Response::HTTP_UNAUTHORIZED);

        $I->login($this->user['email']);

        $I->sendGET('api/achievements/region/' . ($this->region['id'] + 10));
        $I->seeResponseCodeIs(Response::HTTP_FORBIDDEN);

        $I->sendGET('api/achievements/region/' . $this->region['id']);
        $I->seeResponseCodeIs(HttpCode::OK);
        $expected = $this->achievementToArrayForApi($this->achievement);

        $I->seeResponseContainsJson([$expected]);

        $I->dontSeeResponseContainsJson(['id' => $this->otherAchievement->id]);
    }

    public function awardingAchievementForUser(ApiTester $I): void
    {
        // Not allowed logged out
        $I->sendGET("api/achievements/{$this->achievement->id}/users");
        $I->seeResponseCodeIs(Response::HTTP_UNAUTHORIZED);
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPOST("api/achievements/{$this->achievement->id}/users/{$this->user['id']}", []);
        $I->seeResponseCodeIs(Response::HTTP_UNAUTHORIZED);

        $I->login($this->user['email']);

        // Not allowed as normal user
        $I->sendGET("api/achievements/{$this->achievement->id}/users");
        $I->seeResponseCodeIs(Response::HTTP_FORBIDDEN);
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPOST("api/achievements/{$this->achievement->id}/users/{$this->user['id']}", []);
        $I->seeResponseCodeIs(Response::HTTP_FORBIDDEN);

        // Allowed as admin
        $I->login($this->admin['email']);

        // Awarding achievement
        $I->sendGET("api/achievements/{$this->achievement->id}/users");
        $I->seeResponseCodeIs(Response::HTTP_OK);
        $I->dontSeeResponseJsonMatchesJsonPath('$[0]');

        // Not allowed to award oneself
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPOST("api/achievements/{$this->achievement->id}/users/{$this->admin['id']}", []);
        $I->seeResponseCodeIs(Response::HTTP_FORBIDDEN);

        // Awarding achievement
        $I->sendPOST("api/achievements/{$this->achievement->id}/users/{$this->user['id']}", ['notice' => 'notice']);
        $I->seeResponseCodeIs(Response::HTTP_OK);

        // Seeing awarded achievement
        $I->sendGET("api/achievements/{$this->achievement->id}/users");
        $I->seeResponseCodeIs(Response::HTTP_OK);
        $I->seeResponseContainsJson([[
            'user' => ['id' => $this->user['id']],
            'reviewer' => ['id' => $this->admin['id']],
            'achievementId' => $this->achievement->id,
            'notice' => 'notice',
        ]]);
        $awardedId = $I->grabEntryFromDatabase('fs_foodsaver_has_achievement', ['foodsaver_id' => $this->user['id'], 'achievement_id' => $this->achievement->id])['id'];

        // Updating awarded achievement
        $I->sendPATCH("api/achievements/awarded/{$awardedId}", [
            'notice' => 'notice2',
            'validUntil' => 'infinite',
        ]);
        $I->seeResponseCodeIs(Response::HTTP_OK);
        $I->seeResponseContainsJson([
            'notice' => 'notice2',
            'validUntil' => null,
        ]);

        // Revoking awarded achievement
        $I->sendDELETE("api/achievements/awarded/{$awardedId}");
        $I->seeResponseCodeIs(Response::HTTP_OK);
        $I->sendGET("api/achievements/{$this->achievement->id}/users");
        $I->dontSeeResponseJsonMatchesJsonPath('$[0]');
    }

    public function administratingAchievements(ApiTester $I): void
    {
        // Error types:
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPost('api/achievements', $this->achievementToArrayForApi($this->achievement));
        $I->seeResponseCodeIs(Response::HTTP_UNAUTHORIZED);
        $I->sendPatch('api/achievements/1', $this->achievementToArrayForApi($this->achievement));
        $I->seeResponseCodeIs(Response::HTTP_UNAUTHORIZED);
        $I->sendDelete('api/achievements/1');
        $I->seeResponseCodeIs(Response::HTTP_UNAUTHORIZED);

        $I->login($this->user['email']);

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPost('api/achievements', $this->achievementToArrayForApi($this->achievement));
        $I->seeResponseCodeIs(Response::HTTP_FORBIDDEN);
        $I->sendPatch('api/achievements/1', $this->achievementToArrayForApi($this->achievement));
        $I->seeResponseCodeIs(Response::HTTP_FORBIDDEN);
        $I->sendDelete('api/achievements/1');
        $I->seeResponseCodeIs(Response::HTTP_FORBIDDEN);

        $I->login($this->admin['email']);

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPost('api/achievements', []);
        $I->seeResponseCodeIs(Response::HTTP_UNPROCESSABLE_ENTITY);
        $I->sendPatch('api/achievements/1', []);
        $I->seeResponseCodeIs(Response::HTTP_UNPROCESSABLE_ENTITY);

        $I->sendPatch('api/achievements/10', $this->achievementToArrayForApi($this->achievement));
        $I->seeResponseCodeIs(Response::HTTP_NOT_FOUND);
        $I->sendDelete('api/achievements/10');
        $I->seeResponseCodeIs(Response::HTTP_NOT_FOUND);

        // Adding
        $I->sendPost('api/achievements', $this->achievementToArrayForApi($this->achievement));
        $I->seeResponseCodeIs(Response::HTTP_OK);
        $this->achievement->id = $I->grabDataFromResponseByJsonPath('$.id')[0];
        $I->seeInDatabase('fs_achievement', $this->achievementToArray($this->achievement));

        // Editing
        $this->achievement->name .= 'more text';
        $I->sendPatch('api/achievements/' . $this->achievement->id, $this->achievementToArrayForApi($this->achievement));
        $I->seeResponseCodeIs(Response::HTTP_OK);
        $I->seeInDatabase('fs_achievement', $this->achievementToArray($this->achievement));

        // Deleting
        $I->sendDelete('api/achievements/' . $this->achievement->id);
        $I->seeResponseCodeIs(Response::HTTP_OK);
        $I->dontSeeInDatabase('fs_achievement', ['id' => $this->achievement->id]);
    }
}
