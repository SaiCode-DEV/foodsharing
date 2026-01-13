<?php

namespace Foodsharing\RestApi;

use Foodsharing\Lib\Session;
use Foodsharing\Modules\Core\DatabaseNoValueFoundException;
use Foodsharing\Modules\Core\PaginatedForumThreadsForListView;
use Foodsharing\Modules\Core\Pagination;
use Foodsharing\Modules\Region\DTO\ForumThread;
use Foodsharing\Modules\Region\ForumFollowerGateway;
use Foodsharing\Modules\Region\ForumGateway;
use Foodsharing\Modules\Region\ForumTransactions;
use Foodsharing\Modules\Region\RegionTransactions;
use Foodsharing\Modules\Unit\CurrentUserUnitsInterface;
use Foodsharing\Modules\WallPost\EmojiList;
use Foodsharing\Permissions\ForumPermissions;
use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\Request\ParamFetcher;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Annotations as OA1;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

#[OA\Tag(name: 'forum')]
#[OA\Response(response: Response::HTTP_UNAUTHORIZED, description: 'Not logged in')]
class ForumRestController extends AbstractFoodsharingRestController
{
    final public const int DEFAULT_THREADS_PAGE_SIZE = 20;

    public function __construct(
        protected Session $session,
        private readonly RegionTransactions $regionTransactions,
        private readonly ForumGateway $forumGateway,
        private readonly ForumFollowerGateway $forumFollowerGateway,
        private readonly ForumPermissions $forumPermissions,
        private readonly ForumTransactions $forumTransactions,
        private readonly CurrentUserUnitsInterface $currentUserUnits,
    ) {
        parent::__construct($this->session);
    }

    // *** FORUM MANAGEMENT *** //
    // (the following endpoints are for handling forum related actions)

    #[OA\Get(summary: 'Get forum following status.')]
    #[Route('regions/{regionId}/forum/subscriptions', methods: ['GET'], requirements: ['regionId' => Requirement::POSITIVE_INT])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success', content: new OA\JsonContent(type: 'object', properties: [
        new OA\Property('isFollowing', type: 'boolean')
    ]))]
    public function getIsFollowingForum(int $regionId): Response
    {
        $this->assertLoggedIn();
        $isFollowing = $this->forumTransactions->isFollowingForum($regionId);

        return $this->respondOK(['isFollowing' => $isFollowing]);
    }

    #[OA\Put(summary: 'Set forum following status.')]
    #[Route('regions/{regionId}/forum/subscriptions', methods: ['PUT'], requirements: ['regionId' => Requirement::POSITIVE_INT])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success')]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Not permitted to access this forum')]
    public function setFollowingForum(int $regionId, #[MapQueryParameter] bool $isFollowing): Response
    {
        $this->assertLoggedIn();
        if (!$this->currentUserUnits->mayBezirk($regionId)) {
            throw new AccessDeniedHttpException('Not permitted to access this forum');
        }

        $this->forumFollowerGateway->setFollowingForum($regionId, $this->session->id(), $isFollowing);

        return $this->respondOK();
    }

    // *** THREAD MANAGEMENT *** //
    // (the following endpoints are for handling thread related actions)

    #[OA\Get(summary: 'List threads of a forum')]
    #[Route('regions/{regionId}/forum/threads', methods: ['GET'], requirements: ['regionId' => Requirement::POSITIVE_INT])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success', content: new Model(type: PaginatedForumThreadsForListView::class))]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Not permitted to access this forum')]
    public function listThreads(
        int $regionId,
        #[MapQueryParameter] ?int $subforumId,
        #[MapQueryParameter] ?int $limit,
        #[MapQueryParameter] ?int $offset,
    ): Response {
        $this->assertLoggedIn();

        if (!$this->forumPermissions->mayAccessForum($regionId, $subforumId)) {
            throw new AccessDeniedHttpException('Not permitted to access this forum');
        }

        $subforumId ??= 0;
        $pagination = Pagination::create($limit, $offset, self::DEFAULT_THREADS_PAGE_SIZE);
        $threads = $this->forumGateway->getForumThreadsForListView($regionId, $subforumId, $pagination);

        return $this->respondOK($threads);
    }

    #[OA\Get(summary: 'Returns a forum thread including all posts')]
    #[Route('forum/threads/{threadId}', methods: ['GET'], requirements: ['threadId' => '\d+'])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success', content: new Model(type: ForumThread::class))]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Not permitted to access this thread')]
    public function getThread(int $threadId): Response
    {
        $this->assertLoggedIn();

        if (!$this->forumPermissions->mayAccessThread($threadId)) {
            throw new AccessDeniedHttpException('Not permitted to access this thread');
        }

        $thread = $this->forumTransactions->getFullThread($threadId);

        return $this->respondOK($thread);
    }

    /**
     * Create a thread inside a forum.
     *
     * @OA1\Response(response="200", description="success")
     * @OA1\Response(response="403", description="Insufficient permissions")
     */
    #[Route('forum/{forumId}/{forumSubId}', methods: ['POST'], requirements: ['forumId' => '\d+', 'forumSubId' => '\d'])]
    #[Rest\RequestParam(name: 'title', description: 'title of thread')]
    #[Rest\RequestParam(name: 'body', description: 'post message')]
    #[Rest\RequestParam(name: 'sendMail', description: 'false or true value - send a notification mail for all forum user')]
    public function createThread(int $forumId, int $forumSubId, ParamFetcher $paramFetcher): Response
    {
        if (!$this->session->id()) {
            throw new UnauthorizedHttpException('');
        }
        if (!$this->forumPermissions->mayAccessForum($forumId, $forumSubId)) {
            throw new AccessDeniedHttpException();
        }

        $body = trim($paramFetcher->get('body'));
        $title = trim($paramFetcher->get('title'));
        $sendMail = $paramFetcher->get('sendMail') ?? false;
        $regionDetails = $this->regionTransactions->getRegionDetails($forumId);
        $postActiveWithoutModeration = ($this->session->isVerified() && !$regionDetails['moderated']) || $this->currentUserUnits->isAmbassadorForRegion([$forumId]);

        $threadId = $this->forumTransactions->createThread($this->session->id(), $title, $body, $regionDetails, $forumSubId, $postActiveWithoutModeration, $sendMail);

        return $this->getThread($threadId);
    }

    /**
     * Change attributes for a thread: Stickiness, activate thread, status.
     *
     * @OA1\Response(response="200", description="success")
     * @OA1\Response(response="403", description="Insufficient permissions")
     */
    #[Route('forum/thread/{threadId}', methods: ['PATCH'], requirements: ['threadId' => '\d+'])]
    #[Rest\RequestParam(name: 'stickiness', nullable: true, default: null, description: 'should thread be pinned to the top of forum?')]
    #[Rest\RequestParam(name: 'isActive', nullable: true, default: null, description: 'should a thread in a moderated forum be activated?')]
    #[Rest\RequestParam(name: 'status', nullable: true, default: null, description: 'if the thread is open or closed')]
    #[Rest\RequestParam(name: 'title', nullable: true, default: null, description: 'the title of the thread')]
    public function patchThread(int $threadId, ParamFetcher $paramFetcher): Response
    {
        if (!$this->session->id()) {
            throw new UnauthorizedHttpException('');
        }

        $mayModerate = $this->forumPermissions->mayModerate($threadId);

        $stickiness = $paramFetcher->get('stickiness');
        if (!is_null($stickiness)) {
            if (!$mayModerate) {
                throw new AccessDeniedHttpException();
            }
            if (is_int($stickiness)) {
                $this->forumGateway->setStickiness($threadId, $stickiness);
            }
        }
        $isActive = $paramFetcher->get('isActive');
        if ($isActive === true) {
            if (!$mayModerate) {
                throw new AccessDeniedHttpException();
            }
            if (!$this->forumPermissions->mayModerate($threadId)) {
                throw new AccessDeniedHttpException();
            }
            $this->forumTransactions->activateThread($threadId);
        }
        $status = $paramFetcher->get('status');
        if (!is_null($status)) {
            if (!$mayModerate) {
                throw new AccessDeniedHttpException();
            }
            $this->forumGateway->setThreadStatus($threadId, intval($status));
        }

        $title = $paramFetcher->get('title');
        if (!is_null($title)) {
            if (!$this->forumPermissions->mayRename($threadId)) {
                throw new AccessDeniedHttpException();
            }
            $this->forumGateway->setThreadTitle($threadId, trim($title));
        }

        return $this->getThread($threadId);
    }

    #[OA\Delete(summary: 'Deletes a non-activated forum thread')]
    #[Route('forum/thread/{threadId}', methods: ['DELETE'], requirements: ['postId' => Requirement::POSITIVE_INT])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success')]
    #[OA\Response(response: Response::HTTP_NOT_FOUND, description: 'Thread does not exist')]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Not permitted')]
    public function deleteThread(int $threadId): Response
    {
        $this->assertLoggedIn();

        try {
            $thread = $this->forumGateway->getThread($threadId);
        } catch (DatabaseNoValueFoundException) {
            throw new NotFoundHttpException('Thread does not exist');
        }
        if (!$this->forumPermissions->mayDeleteThread($thread)) {
            throw new AccessDeniedHttpException('Not permitted');
        }

        $this->forumTransactions->deleteThread($threadId);

        return $this->respondOK();
    }

    /**
     * request email notifications for activities in at thread.
     *
     * @OA1\Response(response="200", description="success")
     * @OA1\Response(response="403", description="Insufficient permissions")
     */
    #[Route('forum/thread/{threadId}/follow/email', methods: ['POST'], requirements: ['threadId' => '\d+'])]
    public function followThreadByEmail(int $threadId): Response
    {
        if (!$this->session->id()) {
            throw new UnauthorizedHttpException('');
        }
        if (!$this->forumPermissions->mayAccessThread($threadId)) {
            throw new AccessDeniedHttpException();
        }
        $this->forumFollowerGateway->followThreadByEmail($this->session->id(), $threadId);

        return $this->handleView($this->view([]));
    }

    /**
     * request bell notifications for activities in a thread.
     *
     * @OA1\Response(response="200", description="success")
     * @OA1\Response(response="403", description="Insufficient permissions")
     */
    #[Route('forum/thread/{threadId}/follow/bell', methods: ['POST'], requirements: ['threadId' => '\d+'])]
    public function followThreadByBell(int $threadId): Response
    {
        if (!$this->session->id()) {
            throw new UnauthorizedHttpException('');
        }
        if (!$this->forumPermissions->mayAccessThread($threadId)) {
            throw new AccessDeniedHttpException();
        }

        $this->forumFollowerGateway->followThreadByBell($this->session->id(), $threadId);

        return $this->handleView($this->view([]));
    }

    /**
     * Remove email notifications for activities in a thread.
     *
     * @OA1\Response(response="200", description="success")
     * @OA1\Response(response="403", description="Insufficient permissions")
     */
    #[Route('forum/thread/{threadId}/follow/email', methods: ['DELETE'], requirements: ['threadId' => '\d+'])]
    public function unfollowThreadByEmail(int $threadId): Response
    {
        if (!$this->session->id()) {
            throw new UnauthorizedHttpException('');
        }
        if (!$this->forumPermissions->mayAccessThread($threadId)) {
            throw new AccessDeniedHttpException();
        }

        $this->forumFollowerGateway->unfollowThreadByEmail($this->session->id(), $threadId);

        return $this->handleView($this->view([]));
    }

    /**
     * Remove bell notifications for activities in a thread.
     *
     * @OA1\Response(response="200", description="success")
     * @OA1\Response(response="403", description="Insufficient permissions")
     */
    #[Route('forum/thread/{threadId}/follow/bell', methods: ['DELETE'], requirements: ['threadId' => '\d+'])]
    public function unfollowThreadByBell(int $threadId): Response
    {
        if (!$this->session->id()) {
            throw new UnauthorizedHttpException('');
        }
        if (!$this->forumPermissions->mayAccessThread($threadId)) {
            throw new AccessDeniedHttpException();
        }

        $this->forumFollowerGateway->unfollowThreadByBell($this->session->id(), $threadId);

        return $this->handleView($this->view([]));
    }

    // *** POST MANAGEMENT *** //
    // (the following endpoints are for handling post related actions)

    /**
     * Create a post inside a thread.
     *
     * @OA1\Response(response="200", description="success")
     * @OA1\Response(response="403", description="Insufficient permissions")
     */
    #[Route('forum/thread/{threadId}/posts', methods: ['POST'], requirements: ['threadId' => '\d+'])]
    #[Rest\RequestParam(name: 'body', description: 'post message')]
    public function createPost(int $threadId, ParamFetcher $paramFetcher): Response
    {
        if (!$this->session->id()) {
            throw new UnauthorizedHttpException('');
        }
        if (!$this->forumPermissions->mayPostToThread($threadId)) {
            throw new AccessDeniedHttpException();
        }

        $body = trim($paramFetcher->get('body'));
        $this->forumTransactions->addPostToThread($this->session->id(), $threadId, $body);

        return $this->handleView($this->view([], Response::HTTP_OK));
    }

    /**
     * Delete a forum post.
     *
     * @OA1\Response(response="200", description="success")
     * @OA1\Response(response="403", description="Insufficient permissions")
     * @OA1\Response(response="404", description="Post does not exist")
     */
    #[Route('forum/post/{postId}', methods: ['DELETE'], requirements: ['postId' => '\d+'])]
    public function deletePost(int $postId): Response
    {
        if (!$this->session->id()) {
            throw new UnauthorizedHttpException('');
        }

        $post = $this->forumGateway->getPost($postId);
        if (!$post) {
            throw new NotFoundHttpException();
        }
        if (!$this->forumPermissions->mayDeletePost($post['author_id'])) {
            throw new AccessDeniedHttpException();
        }

        $this->forumTransactions->deletePostFromThread($postId, $this->session->id());

        return $this->handleView($this->view([]));
    }

    #[OA\Patch(summary: 'Hide a forum post.')]
    #[Route('forum/post/{postId}/hide', methods: ['PATCH'], requirements: ['postId' => Requirement::POSITIVE_INT])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success')]
    #[OA\Response(response: Response::HTTP_BAD_REQUEST, description: 'Post is already hidden.')]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Insufficient permissions')]
    #[OA\Response(response: Response::HTTP_NOT_FOUND, description: 'Post does not exist')]
    #[Rest\RequestParam(name: 'reason', description: 'hiding reason', requirements: '..{0,255}')]
    public function hidePost(int $postId, ParamFetcher $paramFetcher): Response
    {
        $this->assertLoggedIn();

        $post = $this->forumGateway->getPost($postId);
        if (!$post) {
            throw new NotFoundHttpException();
        }
        if (!$this->forumPermissions->mayHidePost($postId)) {
            throw new AccessDeniedHttpException();
        }
        $reason = $paramFetcher->get('reason');

        if ($this->forumGateway->isPostHidden($postId)) {
            throw new BadRequestHttpException();
        }

        $this->forumTransactions->hidePost($postId, $this->session->id(), $reason);

        return $this->respondOK();
    }

    #[OA\Delete(summary: 'Restore a hidden forum post')]
    #[Route('forum/post/{postId}/hide', methods: ['DELETE'], requirements: ['postId' => Requirement::POSITIVE_INT])]
    #[OA\Response(response: Response::HTTP_OK, description: 'success')]
    #[OA\Response(response: Response::HTTP_BAD_REQUEST, description: 'Post is not hidden.')]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Insufficient permissions')]
    #[OA\Response(response: Response::HTTP_NOT_FOUND, description: 'Post does not exist')]
    public function restorePost(int $postId): Response
    {
        $this->assertLoggedIn();

        $post = $this->forumGateway->getPost($postId);
        if (!$post) {
            throw new NotFoundHttpException();
        }
        if (!$this->forumPermissions->mayRestorePost($postId)) {
            throw new AccessDeniedHttpException();
        }

        if (!$this->forumTransactions->restorePost($postId)) {
            throw new BadRequestHttpException();
        }

        return $this->respondOK();
    }

    // *** REACTION MANAGEMENT *** //
    // (the following endpoints are for handling reaction related actions)

    /**
     * Adds an emoji reaction to a post. An emoji is an arbitrary string but needs to be supported by the frontend.
     *
     * @OA1\Response(response="200", description="success")
     * @OA1\Response(response="403", description="Insufficient permissions")
     * @OA1\Response(response="404", description="Post does not exist")
     */
    #[Route('forum/post/{postId}/reaction/{emoji}', methods: ['POST'], requirements: ['postId' => '\d+', 'emoji' => '\w+'])]
    public function addReaction(int $postId, string $emoji): Response
    {
        if (!$this->session->id()) {
            throw new UnauthorizedHttpException('');
        }
        EmojiList::assertIsValidEmoji($emoji);

        $threadId = $this->forumGateway->getThreadForPost($postId);

        if (is_null($threadId)) {
            throw new NotFoundHttpException();
        }
        if (!$this->forumPermissions->mayAccessThread($threadId)) {
            throw new AccessDeniedHttpException();
        }

        $this->forumTransactions->addReaction($this->session->id(), $postId, $emoji);

        return $this->handleView($this->view([]));
    }

    /**
     * Remove an emoji reaction the logged in user has given from a post.
     *
     * @OA1\Response(response="200", description="Success")
     * @OA1\Response(response="403", description="Insufficient permissions")
     * @OA1\Response(response="404", description="Post does not exist")
     */
    #[Route('forum/post/{postId}/reaction/{emoji}', methods: ['DELETE'], requirements: ['postId' => '\d+', 'emoji' => '\w+'])]
    public function deleteReaction(int $postId, string $emoji): Response
    {
        if (!$this->session->id()) {
            throw new UnauthorizedHttpException('');
        }
        EmojiList::assertIsValidEmoji($emoji);

        $threadId = $this->forumGateway->getThreadForPost($postId);

        if (is_null($threadId)) {
            throw new NotFoundHttpException();
        }
        if (!$this->forumPermissions->mayAccessThread($threadId)) {
            throw new AccessDeniedHttpException();
        }

        $this->forumTransactions->removeReaction($this->session->id(), $postId, $emoji);

        return $this->handleView($this->view([]));
    }
}
