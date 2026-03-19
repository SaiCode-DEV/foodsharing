<?php

declare(strict_types=1);

namespace Tests\Api;

use Carbon\Carbon;
use Codeception\Util\HttpCode;
use Tests\Support\ApiTester;

/**
 * @group api-group-2
 */
class ForumEditApiCest
{
    private $user;
    private $region;
    private $thread;

    final public function _before(ApiTester $I): void
    {
        $this->user = $I->createFoodsaver();

        $this->region = $I->createRegion(fillMailbox: false);
        $I->addRegionMember($this->region['id'], $this->user['id']);

        // explicitly pass the current time to avoid timing issues with the
        // faker's random date generation causing the post to be created too far
        // in the past, making it uneditable
        $this->thread = $I->addForumThread($this->region['id'], $this->user['id'], false, ['time' => (new \DateTime())->format('Y-m-d H:i:s')]);
    }

    public function editOwnPostSucceeds(ApiTester $I): void
    {
        $postId = $this->thread['post']['id'];

        $I->login($this->user['email']);
        $I->sendPatch('api/forum/posts/' . $postId, ['body' => 'Edited by author']);
        $I->seeResponseCodeIs(HttpCode::OK);

        // Verify DB update
        $I->seeInDatabase('fs_theme_post', ['id' => $postId, 'body' => 'Edited by author']);
        $lastEdited = $I->grabFromDatabase('fs_theme_post', 'last_edited_at', ['id' => $postId]);
        $I->assertNotEmpty($lastEdited, 'last_edited_at should be set after editing');
    }

    public function editForeignPostReturnsForbidden(ApiTester $I): void
    {
        $postId = $this->thread['post']['id'];

        $foreigner = $I->createFoodsaver();
        $I->login($foreigner['email']);
        $I->sendPatch('api/forum/posts/' . $postId, ['body' => 'I am not the author']);
        $I->seeResponseCodeIs(HttpCode::FORBIDDEN);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson(['message' => 'You do not have permission to edit this post', 'code' => HttpCode::FORBIDDEN]);
    }

    public function editConflictWhenNewPostAddedReturnsConflict(ApiTester $I): void
    {
        $postId = $this->thread['post']['id'];

        // Add a newer post to the thread
        $user_other = $I->createFoodsaver();
        $I->addRegionMember($this->region['id'], $user_other['id']);
        $I->login($user_other['email']);
        $I->sendPost('api/forum/threads/' . $this->thread['id'] . '/posts', ['body' => 'A newer reply']);
        $I->seeResponseCodeIs(HttpCode::OK);

        // Original author tries to edit original post
        $I->login($this->user['email']);
        $I->sendPatch('api/forum/posts/' . $postId, ['body' => 'Edit after someone replied']);
        $I->seeResponseCodeIs(HttpCode::CONFLICT);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson(['message' => 'Cannot edit as another post was added meanwhile', 'code' => HttpCode::CONFLICT]);
    }

    public function editExpiredWindowReturnsBadRequest(ApiTester $I): void
    {
        $postId = $this->thread['post']['id'];

        // Move post time to the past beyond edit window (10 minutes)
        $old = Carbon::now()->subSeconds(601)->format('Y-m-d H:i:s');
        $I->updateInDatabase('fs_theme_post', ['time' => $old], ['id' => $postId]);

        $I->login($this->user['email']);
        $I->sendPatch('api/forum/posts/' . $postId, ['body' => 'Attempt edit after expiry']);
        $I->seeResponseCodeIs(HttpCode::BAD_REQUEST);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson(['message' => 'Edit window expired for this post', 'code' => HttpCode::BAD_REQUEST]);
    }
}
