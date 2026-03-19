<?php

declare(strict_types=1);

namespace Tests\Unit;

use Codeception\Test\Unit;
use Foodsharing\Lib\Session;
use Foodsharing\Modules\Bell\BellGateway;
use Foodsharing\Modules\Bell\BellTransactions;
use Foodsharing\Modules\Foodsaver\FoodsaverGateway;
use Foodsharing\Modules\Group\GroupFunctionGateway;
use Foodsharing\Modules\Reaction\ReactionTransactions;
use Foodsharing\Modules\Region\ForumFollowerGateway;
use Foodsharing\Modules\Region\ForumGateway;
use Foodsharing\Modules\Region\ForumTransactions;
use Foodsharing\Modules\Region\RegionGateway;
use Foodsharing\Modules\Settings\SettingsGateway;
use Foodsharing\Modules\Unit\CurrentUserUnitsInterface;
use Foodsharing\Permissions\ForumPermissions;
use Foodsharing\RestApi\Models\Forum\CreateThreadData;
use Foodsharing\Utility\EmailHelper;
use Foodsharing\Utility\FlashMessageHelper;
use Foodsharing\Utility\Sanitizer;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Contracts\Translation\TranslatorInterface;
use Tests\Support\UnitTester;

class ForumTransactionsTest extends Unit
{
    protected UnitTester $tester;

    protected ?Session $session = null;
    protected ?Sanitizer $sanitizerService = null;
    protected ?EmailHelper $emailHelper = null;
    protected ?FlashMessageHelper $flashMessageHelper = null;
    protected ?TranslatorInterface $translator = null;

    private ?FoodsaverGateway $foodsaverGateway = null;
    private ?ForumGateway $forumGateway = null;
    private ?ForumFollowerGateway $forumFollowerGateway = null;
    private ?RegionGateway $regionGateway = null;
    private ?GroupFunctionGateway $groupFunctionGateway = null;
    private ?BellTransactions $bellTransaction = null;
    private ?BellGateway $bellGateway = null;
    private ?SettingsGateway $settingsGateway = null;
    private ?ReactionTransactions $reactionTransactions = null;
    private ?CurrentUserUnitsInterface $currentUserUnits = null;
    private ?ForumPermissions $forumPermissions = null;
    private ?ForumTransactions $transaction = null;
    private $user;
    private $user1;
    private $user2;
    private $user3;
    private $region;

    public function _before()
    {
        $this->foodsaverGateway = $this->tester->get(FoodsaverGateway::class);
        $this->forumGateway = $this->tester->get(ForumGateway::class);
        $this->forumFollowerGateway = $this->tester->get(ForumFollowerGateway::class);
        $this->regionGateway = $this->tester->get(RegionGateway::class);
        $this->groupFunctionGateway = $this->tester->get(GroupFunctionGateway::class);
        $this->bellTransaction = $this->tester->get(BellTransactions::class);
        $this->bellGateway = $this->createMock(BellGateway::class);
        $this->settingsGateway = $this->tester->get(SettingsGateway::class);
        $this->reactionTransactions = $this->tester->get(ReactionTransactions::class);
        $this->session = $this->tester->get(Session::class);
        $this->sanitizerService = $this->tester->get(Sanitizer::class);
        $this->emailHelper = $this->tester->get(EmailHelper::class);
        $this->flashMessageHelper = $this->tester->get(FlashMessageHelper::class);
        $this->translator = $this->tester->get(TranslatorInterface::class);
        $this->currentUserUnits = $this->createMock(CurrentUserUnitsInterface::class);
        $this->forumPermissions = $this->tester->get(ForumPermissions::class);

        $this->transaction = new ForumTransactions($this->foodsaverGateway, $this->forumGateway, $this->forumFollowerGateway,
            $this->session, $this->regionGateway, $this->sanitizerService, $this->emailHelper, $this->flashMessageHelper, $this->translator, $this->groupFunctionGateway, $this->bellTransaction,
            $this->bellGateway, $this->settingsGateway, $this->reactionTransactions, $this->currentUserUnits, $this->forumPermissions);

        // Prepare database content
        $this->user = $this->tester->createFoodsaver();
        $this->user1 = $this->tester->createFoodsaver();
        $this->user2 = $this->tester->createFoodsaver();
        $this->user3 = $this->tester->createFoodsaver();

        $this->region = $this->tester->createRegion(fillMailbox: false);
        $this->tester->addRegionMember($this->region['id'], $this->user['id']);
        $this->tester->addRegionMember($this->region['id'], $this->user1['id']);
        $this->tester->addRegionMember($this->region['id'], $this->user2['id']);
        $this->tester->addRegionMember($this->region['id'], $this->user3['id']);
    }

    public function testMentionedUsersOnceGetBellForCreateThread(): void
    {
        $this->bellGateway->expects($this->once())->method('addBellForUsers')->with(
            $this->equalTo([$this->user1['id'], $this->user2['id'], $this->user3['id']]), $this->anything());
        $thread = new CreateThreadData();
        $thread->title = 'Title';
        $thread->body = 'Besprechung, @' . $this->user1['id'] .
            ' übernimmt du verifizieren @' . $this->user2['id'] . ' und @' . $this->user3['id'] . ' für @' . $this->user2['id'] .
            ' eine Einführungsabholung durchführen. ';
        $this->transaction->createThread($this->user1['id'], $thread, $this->region, false, false);
    }

    public function testEditPostByAuthorWithinWindowUpdatesPost(): void
    {
        $thread = $this->tester->addForumThread($this->region['id'], $this->user['id'], false, ['time' => (new \DateTime())->format('Y-m-d H:i:s')]);
        $post = $thread['post'];

        // simulate logged in author without calling Session::login (avoid PHP session conflicts)
        $this->setSessionUserId($this->user['id']);

        $this->transaction->editPost($post['id'], 'Updated body by author');

        $dbPost = $this->forumGateway->getPost($post['id']);
        $this->assertEquals('Updated body by author', $dbPost['body']);
        $this->assertNotEmpty($dbPost['last_edited_at']);
    }

    public function testEditForeignPostThrows403(): void
    {
        $thread = $this->tester->addForumThread($this->region['id'], $this->user['id'], false, ['time' => (new \DateTime())->format('Y-m-d H:i:s')]);
        $post = $thread['post'];
        $this->expectException(AccessDeniedHttpException::class);

        // simulate another user logged in safely
        $this->setSessionUserId($this->user1['id']);

        $this->transaction->editPost($post['id'], 'Malicious edit');
    }

    public function testEditConflictWhenNewPostExists(): void
    {
        $thread = $this->tester->addForumThread($this->region['id'], $this->user['id'], false, ['time' => (new \DateTime())->format('Y-m-d H:i:s')]);
        $post = $thread['post'];

        // another user adds a new post
        $this->tester->addForumThreadPost($thread['id'], $this->user1['id'], ['body' => 'A reply']);

        $this->expectException(ConflictHttpException::class);
        $this->expectExceptionMessage('Cannot edit as another post was added meanwhile');

        // original author tries to edit
        $this->setSessionUserId($this->user['id']);
        $this->transaction->editPost($post['id'], 'Late edit');
    }

    public function testEditExpiredWindowThrows(): void
    {
        $thread = $this->tester->addForumThread($this->region['id'], $this->user['id'], false, ['time' => (new \DateTime())->format('Y-m-d H:i:s')]);
        $post = $thread['post'];

        // set post time to 605 seconds ago
        $old = (new \DateTime())->sub(new \DateInterval('PT605S'))->format('Y-m-d H:i:s');
        $this->tester->updateInDatabase('fs_theme_post', ['time' => $old], ['id' => $post['id']]);

        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage('Edit window expired for this post');

        $this->setSessionUserId($this->user['id']);
        $this->transaction->editPost($post['id'], 'Too late edit');
    }

    public function testEditNonExistingPostThrows(): void
    {
        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('Post not found');

        $this->setSessionUserId($this->user['id']);
        $this->transaction->editPost(999999, 'Edit non-existing post');
    }

    private function setSessionUserId(int $id): void
    {
        $session = $this->session;

        $storage = new \Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage();
        $sf = new \Symfony\Component\HttpFoundation\Session\Session($storage);
        $sf->start();

        $ref = new \ReflectionClass($session);
        $prop = $ref->getProperty('symfonySession');
        $prop->setAccessible(true);
        $prop->setValue($session, $sf);

        $propInit = $ref->getProperty('initialized');
        $propInit->setAccessible(true);
        $propInit->setValue($session, true);

        $session->set('userId', $id);
    }
}
