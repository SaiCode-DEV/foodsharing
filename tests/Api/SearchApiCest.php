<?php

declare(strict_types=1);

namespace Tests\Api;

use Codeception\Example;
use Codeception\Util\HttpCode;
use Tests\Support\ApiTester;

/**
 * @group api-group-3
 */
class SearchApiCest
{
    private array $user1;
    private array $user2;
    private array $region1;
    private array $region2;
    private array $userAmbassador;
    private array $userOrga;
    private array $region1ForumThread;
    private array $region2ForumThread;
    private array $region1AmbassadorForumThread;

    public function _before(ApiTester $I): void
    {
        $this->region1 = $I->createRegion();
        $this->region2 = $I->createRegion();

        $this->user1 = $I->createFoodsaver(null, ['bezirk_id' => $this->region1['id']]);
        $I->addRegionMember($this->region1['id'], $this->user1['id']);
        $this->user2 = $I->createFoodsaver(null, ['bezirk_id' => $this->region2['id']]);
        $I->addRegionMember($this->region2['id'], $this->user2['id']);

        $this->userAmbassador = $I->createAmbassador();
        $I->addRegionMember($this->region1['id'], $this->userAmbassador['id']);
        $I->addRegionAdmin($this->region1['id'], $this->userAmbassador['id']);

        $this->userOrga = $I->createOrga();

        $this->region1ForumThread = $I->addForumThread($this->region1['id'], $this->user1['id'], false, [
            'name' => 'Testbeitrag im Forum der Region 1'
        ]);
        $this->region2ForumThread = $I->addForumThread($this->region2['id'], $this->user2['id']);
        $this->region1AmbassadorForumThread = $I->addForumThread($this->region1['id'], $this->user1['id'], true);
    }

    // ========================= forum search endpoint ================================

    public function canOnlySearchInForumWhenLoggedIn(ApiTester $I)
    {
        $I->sendGET('api/search/forum/' . $this->region1['id'] . '/0?q=test');
        $I->seeResponseCodeIs(HttpCode::UNAUTHORIZED);

        $I->login($this->user1['email']);
        $I->sendGET('api/search/forum/' . $this->region1['id'] . '/0?q=test');
        $I->seeResponseCodeIs(HttpCode::OK);
    }

    public function canSearchInForumOfMyRegions(ApiTester $I)
    {
        $I->login($this->user1['email']);
        $this->canFindForumThread($I, $this->region1['id'], false, $this->region1ForumThread);
    }

    public function canNotSearchInOtherForums(ApiTester $I)
    {
        $query = substr((string)$this->region2ForumThread['name'], 0, 5);

        $I->login($this->user1['email']);
        $I->sendGET('api/search/forum/' . $this->region2['id'] . '/0?q=' . urlencode($query));
        $I->seeResponseCodeIs(HttpCode::FORBIDDEN);
    }

    public function canNotSearchForEmptyString(ApiTester $I)
    {
        $I->login($this->user1['email']);
        $I->sendGET('api/search/forum/' . $this->region1['id'] . '/0?q=');
        $I->seeResponseCodeIs(HttpCode::BAD_REQUEST);
    }

    public function canOnlySearchInAmbassadorForumAsAmbassador(ApiTester $I)
    {
        $query = substr((string)$this->region1AmbassadorForumThread['name'], 0, 5);

        $I->login($this->user1['email']);
        $I->sendGET('api/search/forum/' . $this->region1['id'] . '/1?q=' . urlencode($query));
        $I->seeResponseCodeIs(HttpCode::FORBIDDEN);

        $I->login($this->userAmbassador['email']);
        $this->canFindForumThread($I, $this->region1['id'], true, $this->region1AmbassadorForumThread);
    }

    public function canSearchInEveryForumAsOrga(ApiTester $I)
    {
        $I->login($this->userOrga['email']);

        $this->canFindForumThread($I, $this->region1['id'], false, $this->region1ForumThread);
        $this->canFindForumThread($I, $this->region2['id'], false, $this->region2ForumThread);
        $this->canFindForumThread($I, $this->region1['id'], true, $this->region1AmbassadorForumThread);
    }

    private function canFindForumThread(ApiTester $I, int|string $regionId, bool $ambassadorForum, array $thread)
    {
        $query = $thread['name'];
        $subforumId = $ambassadorForum ? 1 : 0;

        $I->sendGET("api/search/forum/$regionId/$subforumId?q=" . urlencode($query));
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseContainsJson([
            'id' => $thread['id'],
            'name' => $thread['name']
        ]);
    }

    // ========================= user search endpoint ================================

    public function canOnlySearchWhenLoggedIn(ApiTester $I): void
    {
        $I->sendGET('api/search/user?q=test');
        $I->seeResponseCodeIs(HttpCode::UNAUTHORIZED);

        $I->login($this->user1['email']);
        $I->sendGET('api/search/user?q=test');
        $I->seeResponseCodeIs(HttpCode::OK);
    }

    public function canSearchInSameRegion(ApiTester $I): void
    {
        $I->login($this->user1['email']);
        $I->sendGET("api/search/user?q={$this->user1['name']}&regionId={$this->region1['id']}");
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseIsJson();
        $I->canSeeResponseContainsJson(['id' => $this->user1['id']]);
    }

    public function canNotSearchInOtherRegions(ApiTester $I): void
    {
        $I->login($this->user1['email']);
        $I->sendGET("api/search/user?q={$this->user2['name']}&regionId={$this->region2['id']}");
        $I->seeResponseCodeIs(HttpCode::FORBIDDEN);
    }

    public function canSearchInAllRegionsAsOrga(ApiTester $I): void
    {
        $I->login($this->userOrga['email']);

        $I->sendGET("api/search/user?q={$this->user1['name']}&regionId={$this->region1['id']}");
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->canSeeResponseContainsJson(['id' => $this->user1['id']]);

        $I->sendGET("api/search/user?q={$this->user2['name']}&regionId={$this->region2['id']}");
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->canSeeResponseContainsJson(['id' => $this->user2['id']]);
    }

    public function canFindUsersById(ApiTester $I): void
    {
        $I->login($this->userAmbassador['email']);
        $I->sendGET("api/search/user?q={$this->user1['id']}&regionId={$this->region1['id']}");
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseIsJson();
        $I->canSeeResponseContainsJson(['id' => $this->user1['id']]);
    }

    public function canNotFindUsersByIdWhoAreNotMember(ApiTester $I)
    {
        $I->login($this->userAmbassador['email']);
        $I->sendGET("api/search/user?q={$this->user2['id']}&regionId={$this->region1['id']}");
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseIsJson();
        $I->cantSeeResponseContainsJson(['id' => $this->user2['id']]);
    }

    // ========================= general search endpoint ================================
    public function canOnlyUseGeneralSearchWhenLoggedIn(ApiTester $I)
    {
        $I->sendGET('api/search/all?q=test');
        $I->seeResponseCodeIs(HttpCode::UNAUTHORIZED);

        $I->login($this->user1['email']);
        $I->sendGET('api/search/all?q=test');
        $I->seeResponseCodeIs(HttpCode::OK);
    }

    /**
     * @example {"loginUser": 0, "searchUser": 0, "canFind": true, "canSeeFullName": false}
     * @example {"loginUser": 0, "searchUser": 1, "canFind": false, "canSeeFullName": false}
     * @example {"loginUser": 1, "searchUser": 0, "canFind": true, "canSeeFullName": true}
     * @example {"loginUser": 1, "searchUser": 1, "canFind": false, "canSeeFullName": false}
     * @example {"loginUser": 2, "searchUser": 0, "canFind": true, "canSeeFullName": true}
     * @example {"loginUser": 2, "searchUser": 1, "canFind": true, "canSeeFullName": true}
     */
    public function canSearchByFirstName(ApiTester $I, Example $example)
    {
        $loginUsers = [$this->user1, $this->userAmbassador, $this->userOrga];
        $loginUser = $loginUsers[$example['loginUser']];

        $searchUsers = [$this->user1, $this->user2];
        $searchUser = $searchUsers[$example['searchUser']];

        $I->login($loginUser['email']);
        $I->sendGET("api/search/all?q={$searchUser['name']}&global");
        $I->seeResponseCodeIs(HttpCode::OK);

        if ($example['canFind']) {
            $userToFind = [
                'id' => $searchUser['id'],
                'name' => $searchUser['name'],
            ];
            if ($example['canSeeFullName']) {
                $userToFind['last_name'] = $searchUser['nachname'];
            }
            $I->seeResponseContainsJson(['users' => [$userToFind]]);
        } else {
            $I->dontSeeResponseContainsJson(['users' => [[
                'id' => $searchUser['id'],
            ]]]);
        }
    }

    /**
     * @example {"loginUser": 0, "searchUser": 1, "canFind": false}
     * @example {"loginUser": 1, "searchUser": 0, "canFind": true}
     * @example {"loginUser": 1, "searchUser": 1, "canFind": false}
     * @example {"loginUser": 2, "searchUser": 0, "canFind": true}
     * @example {"loginUser": 2, "searchUser": 1, "canFind": true}
     */
    public function canSearchByLastName(ApiTester $I, Example $example)
    {
        $loginUsers = [$this->user1, $this->userAmbassador, $this->userOrga];
        $loginUser = $loginUsers[$example['loginUser']];

        $searchUsers = [$this->user1, $this->user2];
        $searchUser = $searchUsers[$example['searchUser']];

        $I->login($loginUser['email']);
        $I->sendGET("api/search/all?q={$searchUser['nachname']}&global");
        $I->seeResponseCodeIs(HttpCode::OK);

        if ($example['canFind']) {
            $I->seeResponseContainsJson(['users' => [[
                'id' => $searchUser['id'],
            ]]]);
        } else {
            $I->dontSeeResponseContainsJson(['users' => [[
                'id' => $searchUser['id'],
            ]]]);
        }
    }

    public function canUserWithoutRightsSearchForEmailAdresses(ApiTester $I)
    {
        $I->login($this->user1['email']);
        $I->sendGET("api/search/all?q={$this->user1['email']}");
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->cantSeeResponseContainsJson(['users' => [
            0 => [
                'id' => $this->user1['id'],
                'name' => $this->user1['name'],
            ]
        ]]);
    }

    public function canUserWithOrgaRightsSearchForEmailAdresses(ApiTester $I)
    {
        $I->login($this->userOrga['email']);
        $I->sendGET("api/search/all?q={$this->user1['email']}&global");
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->canSeeResponseContainsJson(['users' => [
            0 => [
                'id' => $this->user1['id'],
                'name' => $this->user1['name'],
                'email' => $this->user1['email']
            ]
        ]]);
    }
}
