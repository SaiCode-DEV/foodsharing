<?php

declare(strict_types=1);

namespace Tests\Api;

use Codeception\Util\HttpCode;
use Foodsharing\Modules\Core\DBConstants\Voting\VotingType;
use Tests\Support\ApiTester;

/**
 * @group api-group-2
 */
class GroupApiCest
{
    private array $region;
    private array $ambassador;
    private array $orga;

    public function _before(ApiTester $I): void
    {
        $this->region = $I->createRegion();
        $this->ambassador = $I->createAmbassador();
        $this->orga = $I->createOrga();
    }

    public function deleteGroupFailsForAmbassador(ApiTester $I): void
    {
        $I->login($this->ambassador['email']);
        $I->sendDELETE("api/regions/{$this->region['id']}");
        $I->seeResponseCodeIs(HttpCode::FORBIDDEN);
        $I->seeInDatabase('fs_bezirk', ['id' => $this->region['id']]);
    }

    public function deleteGroupWorksForOrga(ApiTester $I): void
    {
        $I->login($this->orga['email']);
        $I->sendDELETE("api/regions/{$this->region['id']}");
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->dontSeeInDatabase('fs_bezirk', ['id' => $this->region['id']]);
    }

    public function groupDataIsDeleted(ApiTester $I): void
    {
        // Data that should be deleted: forum threads and posts, events, polls, wall posts, achievements, ressources
        $thread1 = $I->addForumThread($this->region['id'], $this->ambassador['id'], false);
        $post1 = $I->addForumThreadPost($thread1['id'], $this->ambassador['id']);
        $thread2 = $I->addForumThread($this->region['id'], $this->ambassador['id'], true);
        $post2 = $I->addForumThreadPost($thread2['id'], $this->ambassador['id']);

        $poll = $I->createPoll($this->region['id'], $this->ambassador['id'], ['type' => VotingType::THUMB_VOTING]);
        for ($i = 0; $i < random_int(3, 5); ++$i) {
            $I->createPollOption($poll['id'], [-1, 0, 1]);
        }
        $I->addVoters($poll['id'], [$this->ambassador['id'], $this->orga['id']]);

        $wallPost = $I->addGroupWallPost($this->ambassador['id'], $this->region['id']);

        // The fs_event foreign key would only null bezirk_id, so events and their
        // wall posts need explicit deletion (#2018)
        $event = $I->createEvents($this->region['id'], $this->ambassador['id']);
        $eventWallPost = $I->createWallpost($this->ambassador['id']);
        $I->haveInDatabase('fs_event_has_wallpost', ['event_id' => $event['id'], 'wallpost_id' => $eventWallPost['id']]);

        $mailboxId = $I->grabFromDatabase('fs_bezirk', 'mailbox_id', ['id' => $this->region['id']]);

        // Delete the region
        $I->login($this->orga['email']);
        $I->sendDELETE("api/regions/{$this->region['id']}");
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->dontSeeInDatabase('fs_bezirk', ['id' => $this->region['id']]);

        // Make sure that the data was deleted
        $I->dontSeeInDatabase('fs_theme', ['id' => $thread1['id']]);
        $I->dontSeeInDatabase('fs_theme_post', ['id' => $post1['id']]);
        $I->dontSeeInDatabase('fs_bezirk_has_theme', ['theme_id' => $thread1['id']]);
        $I->dontSeeInDatabase('fs_theme', ['id' => $thread2['id']]);
        $I->dontSeeInDatabase('fs_theme_post', ['id' => $post2['id']]);
        $I->dontSeeInDatabase('fs_bezirk_has_theme', ['theme_id' => $thread2['id']]);

        $I->dontSeeInDatabase('fs_poll', ['id' => $poll['id']]);
        $I->dontSeeInDatabase('fs_poll_has_options', ['poll_id' => $poll['id']]);
        $I->dontSeeInDatabase('fs_poll_option_has_value', ['poll_id' => $poll['id']]);

        $I->dontSeeInDatabase('fs_wallpost', ['id' => $wallPost['id']]);
        $I->dontSeeInDatabase('fs_bezirk_has_wallpost', ['wallpost_id' => $wallPost['id']]);

        $I->dontSeeInDatabase('fs_event', ['id' => $event['id']]);
        $I->dontSeeInDatabase('fs_wallpost', ['id' => $eventWallPost['id']]);
        $I->dontSeeInDatabase('fs_mailbox', ['id' => $mailboxId]);
    }

    public function emailsInTheMailboxBlockTheDeletion(ApiTester $I): void
    {
        $region = $I->createRegion(null, [], fillMailbox: true);

        $I->login($this->orga['email']);
        $I->sendDELETE("api/regions/{$region['id']}");
        $I->seeResponseCodeIs(HttpCode::CONFLICT);
        // own message so the admin tool shows the actual block reason
        $I->canSeeResponseContains('mailbox');
        $I->seeInDatabase('fs_bezirk', ['id' => $region['id']]);
    }
}
