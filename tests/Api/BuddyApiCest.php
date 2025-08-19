<?php

declare(strict_types=1);

namespace Tests\Api;

use Codeception\Util\HttpCode;
use Foodsharing\Modules\Core\DBConstants\Buddy\BuddyId;
use Tests\Support\ApiTester;

class BuddyApiCest
{
    private $user1;
    private $user2;

    public function _before(ApiTester $I): void
    {
        $this->user1 = $I->createFoodsharer();
        $this->user2 = $I->createFoodsharer();
    }

    public function canOnlySendBuddyRequestWhenLoggedIn(ApiTester $I): void
    {
        $I->sendPUT('api/buddy/' . $this->user2['id']);
        $I->seeResponseCodeIs(HttpCode::UNAUTHORIZED);

        $I->login($this->user1['email']);
        $I->sendPUT('api/buddy/' . $this->user2['id']);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeInDatabase('fs_buddy', [
            'foodsaver_id' => $this->user1['id'],
            'buddy_id' => $this->user2['id'],
            'confirmed' => BuddyId::REQUESTED
        ]);
    }

    public function canAcceptBuddyRequest(ApiTester $I): void
    {
        $I->login($this->user1['email']);
        $I->sendPUT('api/buddy/' . $this->user2['id']);
        $I->seeResponseCodeIs(HttpCode::OK);

        $I->login($this->user2['email']);
        $I->sendPUT('api/buddy/' . $this->user1['id']);
        $I->seeResponseCodeIs(HttpCode::OK);

        $I->seeInDatabase('fs_buddy', [
            'foodsaver_id' => $this->user1['id'],
            'buddy_id' => $this->user2['id'],
            'confirmed' => BuddyId::BUDDY
        ]);
        $I->seeInDatabase('fs_buddy', [
            'foodsaver_id' => $this->user2['id'],
            'buddy_id' => $this->user1['id'],
            'confirmed' => BuddyId::BUDDY
        ]);
    }

    public function buddyRequestIsOverwritten(ApiTester $I): void
    {
        $I->login($this->user1['email']);
        $I->sendPUT('api/buddy/' . $this->user2['id']);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeInDatabase('fs_buddy', [
            'foodsaver_id' => $this->user1['id'],
            'buddy_id' => $this->user2['id'],
            'confirmed' => BuddyId::REQUESTED
        ]);

        $I->sendPUT('api/buddy/' . $this->user2['id']);
        $I->seeResponseCodeIs(HttpCode::BAD_REQUEST);
    }

    public function canRemoveBuddyRequest(ApiTester $I): void
    {
        $I->login($this->user1['email']);
        $I->sendPUT('api/buddy/' . $this->user2['id']);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeInDatabase('fs_buddy', [
            'foodsaver_id' => $this->user1['id'],
            'buddy_id' => $this->user2['id'],
            'confirmed' => BuddyId::REQUESTED
        ]);
        $I->sendDELETE('api/buddy/' . $this->user2['id']);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->dontSeeInDatabase('fs_buddy', [
            'foodsaver_id' => $this->user1['id'],
        ]);
    }

    public function canRemoveAcceptedBuddy(ApiTester $I): void
    {
        $I->login($this->user1['email']);
        $I->sendPUT('api/buddy/' . $this->user2['id']);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->login($this->user2['email']);
        $I->sendPUT('api/buddy/' . $this->user1['id']);
        $I->seeResponseCodeIs(HttpCode::OK);

        $I->seeInDatabase('fs_buddy', [
            'foodsaver_id' => $this->user1['id'],
            'buddy_id' => $this->user2['id'],
            'confirmed' => BuddyId::BUDDY
        ]);
        $I->seeInDatabase('fs_buddy', [
            'foodsaver_id' => $this->user2['id'],
            'buddy_id' => $this->user1['id'],
            'confirmed' => BuddyId::BUDDY
        ]);

        $I->login($this->user1['email']);

        $I->sendDELETE('api/buddy/' . $this->user2['id']);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->dontSeeInDatabase('fs_buddy', [
            'foodsaver_id' => $this->user1['id'],
            'buddy_id' => $this->user2['id'],
        ]);
        $I->seeInDatabase('fs_buddy', [
            'foodsaver_id' => $this->user2['id'],
            'buddy_id' => $this->user1['id'],
            'confirmed' => BuddyId::REQUESTED
        ]);
    }

    public function canNotSendRequestToBuddy(ApiTester $I): void
    {
        $I->haveInDatabase('fs_buddy', [
            'foodsaver_id' => $this->user1['id'],
            'buddy_id' => $this->user2['id'],
            'confirmed' => BuddyId::BUDDY
        ]);
        $I->haveInDatabase('fs_buddy', [
            'foodsaver_id' => $this->user2['id'],
            'buddy_id' => $this->user1['id'],
            'confirmed' => BuddyId::BUDDY
        ]);

        $I->login($this->user1['email']);
        $I->sendPUT('api/buddy/' . $this->user2['id']);
        $I->seeResponseCodeIs(HttpCode::BAD_REQUEST);

        $I->login($this->user2['email']);
        $I->sendPUT('api/buddy/' . $this->user1['id']);
        $I->seeResponseCodeIs(HttpCode::BAD_REQUEST);
    }

    public function canListBuddies(ApiTester $I): void
    {
        $user3 = $I->createFoodsharer();
        $user4 = $I->createFoodsharer();

        // User1 and User2 become buddies
        $I->login($this->user1['email']);
        $I->sendPUT('api/buddy/' . $this->user2['id']);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->login($this->user2['email']);
        $I->sendPUT('api/buddy/' . $this->user1['id']);
        $I->seeResponseCodeIs(HttpCode::OK);

        // User1 requests User3 as a buddy
        $I->login($this->user1['email']);
        $I->sendPUT('api/buddy/' . $user3['id']);
        $I->seeResponseCodeIs(HttpCode::OK);

        // User 4 requests User1 as a buddy
        $I->login($user4['email']);
        $I->sendPUT('api/buddy/' . $this->user1['id']);
        $I->seeResponseCodeIs(HttpCode::OK);

        // User1 requests their buddy list
        $I->login($this->user1['email']);
        $I->sendGET('api/buddy/list');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson([
            'buddies' => [
                [
                    // User1's buddy User2
                    'fsId' => $this->user1['id'],
                    'buddyId' => $this->user2['id'],
                    'name' => $this->user2['name'],
                    'photo' => $this->user2['photo'] ?? null,
                    'confirmed' => 1,
                ]
            ],
            'requests' => [
                'mine' => [
                    [
                        // User1's request to User3
                        'fsId' => $this->user1['id'],
                        'buddyId' => $user3['id'],
                        'name' => $user3['name'],
                        'photo' => $user3['photo'] ?? null,
                        'confirmed' => 0,
                    ],
                ],
                'other' => [
                    [   // User4's request to User1
                        'fsId' => $user4['id'],
                        'buddyId' => $this->user1['id'],
                        'name' => $user4['name'],
                        'photo' => $user4['photo'] ?? null,
                        'confirmed' => 0,
                    ],
                ],
            ]
        ]);
    }
}
