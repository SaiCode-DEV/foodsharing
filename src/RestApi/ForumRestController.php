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
use Foodsharing\RestApi\Models\Forum\CreatePostData;
use Foodsharing\RestApi\Models\Forum\CreateThreadData;
use Foodsharing\RestApi\Models\Forum\PatchPostData;
use Foodsharing\RestApi\Models\Forum\PatchThreadData;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
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
    #[Route('forum/threads/{threadId}', methods: ['GET'], requirements: ['threadId' => Requirement::POSITIVE_INT])]
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

    #[OA\Post(summary: 'Create a thread inside a forum.')]
    #[Route('regions/{regionId}/forum/threads', methods: ['POST'], requirements: ['forumId' => Requirement::POSITIVE_INT, 'forumSubId' => Requirement::DIGITS])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success')]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Not permitted')]
    public function createThread(
        int $regionId,
        #[MapQueryParameter] ?int $subforumId,
        #[MapRequestPayload] CreateThreadData $thread
    ): Response {
        $this->assertLoggedIn();
        $subforumId ??= 0;
        if (!$this->forumPermissions->mayAccessForum($regionId, $subforumId)) {
            throw new AccessDeniedHttpException('Not permitted to access this forum');
        }

        $regionDetails = $this->regionTransactions->getRegionDetails($regionId);
        $postActiveWithoutModeration = ($this->session->isVerified() && !$regionDetails['moderated']) || $this->currentUserUnits->isAmbassadorForRegion([$regionId]);

        $this->forumTransactions->createThread($this->session->id(), $thread, $regionDetails, $subforumId === 1, $postActiveWithoutModeration);

        return $this->respondOK();
    }

    #[OA\Patch(summary: 'Change attributes for a thread: Stickiness, activate thread, status.')]
    #[Route('forum/threads/{threadId}', methods: ['PATCH'], requirements: ['threadId' => Requirement::POSITIVE_INT])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success')]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Not permitted')]
    public function patchThread(int $threadId, #[MapRequestPayload] PatchThreadData $patchData): Response
    {
        $this->assertLoggedIn();

        if (!is_null($patchData->stickiness)) {
            if (!$this->forumPermissions->mayModerate($threadId)) {
                throw new AccessDeniedHttpException('Not permitted');
            }
            $this->forumGateway->setStickiness($threadId, $patchData->stickiness);
        }
        if ($patchData->isActive === true) {
            if (!$this->forumPermissions->mayModerate($threadId)) {
                throw new AccessDeniedHttpException('Not permitted');
            }
            $this->forumTransactions->activateThread($threadId);
        }
        if (!is_null($patchData->status)) {
            if (!$this->forumPermissions->mayModerate($threadId)) {
                throw new AccessDeniedHttpException('Not permitted');
            }
            $this->forumGateway->setThreadStatus($threadId, $patchData->status);
        }

        if (!is_null($patchData->title)) {
            if (!$this->forumPermissions->mayRename($threadId)) {
                throw new AccessDeniedHttpException('Not permitted');
            }
            $this->forumGateway->setThreadTitle($threadId, trim($patchData->title));
        }

        return $this->respondOK();
    }

    #[OA\Delete(summary: 'Deletes a non-activated forum thread')]
    #[Route('forum/threads/{threadId}', methods: ['DELETE'], requirements: ['postId' => Requirement::POSITIVE_INT])]
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

    #[OA\Post(summary: 'Request email notifications for activities in at thread.')]
    #[Route('forum/threads/{threadId}/follow/email', methods: ['POST'], requirements: ['threadId' => Requirement::POSITIVE_INT])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success')]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Not permitted')]
    public function followThreadByEmail(int $threadId): Response
    {
        $this->assertLoggedIn();
        if (!$this->forumPermissions->mayAccessThread($threadId)) {
            throw new AccessDeniedHttpException('Not permitted');
        }
        $this->forumFollowerGateway->followThreadByEmail($this->session->id(), $threadId);

        return $this->respondOK();
    }

    #[OA\Post(summary: 'Request bell notifications for activities in a thread.')]
    #[Route('forum/threads/{threadId}/follow/bell', methods: ['POST'], requirements: ['threadId' => Requirement::POSITIVE_INT])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success')]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Not permitted')]
    public function followThreadByBell(int $threadId): Response
    {
        $this->assertLoggedIn();
        if (!$this->forumPermissions->mayAccessThread($threadId)) {
            throw new AccessDeniedHttpException('Not permitted');
        }

        $this->forumFollowerGateway->followThreadByBell($this->session->id(), $threadId);

        return $this->respondOK();
    }

    #[OA\Delete(summary: 'Removes email notifications for activities in a thread.')]
    #[Route('forum/threads/{threadId}/follow/email', methods: ['DELETE'], requirements: ['threadId' => Requirement::POSITIVE_INT])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success')]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Not permitted')]
    public function unfollowThreadByEmail(int $threadId): Response
    {
        $this->assertLoggedIn();
        if (!$this->forumPermissions->mayAccessThread($threadId)) {
            throw new AccessDeniedHttpException('Not permitted');
        }

        $this->forumFollowerGateway->unfollowThreadByEmail($this->session->id(), $threadId);

        return $this->respondOK();
    }

    #[OA\Delete(summary: 'Removes bell notifications for activities in a thread.')]
    #[Route('forum/threads/{threadId}/follow/bell', methods: ['DELETE'], requirements: ['threadId' => Requirement::POSITIVE_INT])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success')]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Not permitted')]
    public function unfollowThreadByBell(int $threadId): Response
    {
        $this->assertLoggedIn();
        if (!$this->forumPermissions->mayAccessThread($threadId)) {
            throw new AccessDeniedHttpException('Not permitted');
        }

        $this->forumFollowerGateway->unfollowThreadByBell($this->session->id(), $threadId);

        return $this->respondOK();
    }

    // *** POST MANAGEMENT *** //
    // (the following endpoints are for handling post related actions)

    #[OA\Post(summary: 'Creates a post inside a thread.')]
    #[Route('forum/threads/{threadId}/posts', methods: ['POST'], requirements: ['threadId' => Requirement::POSITIVE_INT])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success')]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Not permitted')]
    public function createPost(int $threadId, #[MapRequestPayload] CreatePostData $post): Response
    {
        $this->assertLoggedIn();
        if (!$this->forumPermissions->mayPostToThread($threadId)) {
            throw new AccessDeniedHttpException('Not permitted');
        }

        $this->forumTransactions->addPostToThread($this->session->id(), $threadId, trim($post->body));

        return $this->respondOK();
    }

    #[OA\Delete(summary: 'Deletes a forum post.')]
    #[Route('forum/posts/{postId}', methods: ['DELETE'], requirements: ['postId' => Requirement::POSITIVE_INT])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success')]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Not permitted')]
    #[OA\Response(response: Response::HTTP_NOT_FOUND, description: 'Post does not exist')]
    public function deletePost(int $postId): Response
    {
        $this->assertLoggedIn();

        $post = $this->forumGateway->getPost($postId);
        if (!$post) {
            throw new NotFoundHttpException('Post not found');
        }
        if (!$this->forumPermissions->mayDeletePost($post['author_id'])) {
            throw new AccessDeniedHttpException('Not permitted');
        }

        $this->forumTransactions->deletePostFromThread($postId, $this->session->id());

        return $this->respondOK();
    }

    #[OA\Patch(summary: 'Hide a forum post.')]
    #[Route('forum/posts/{postId}/hidden', methods: ['PATCH'], requirements: ['postId' => Requirement::POSITIVE_INT])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success')]
    #[OA\Response(response: Response::HTTP_BAD_REQUEST, description: 'Post is already hidden.')]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Insufficient permissions')]
    #[OA\Response(response: Response::HTTP_NOT_FOUND, description: 'Post does not exist')]
    public function hidePost(int $postId, #[MapRequestPayload] PatchPostData $patchPostData): Response
    {
        $this->assertLoggedIn();

        $post = $this->forumGateway->getPost($postId);
        if (!$post) {
            throw new NotFoundHttpException('Post not found');
        }
        if (!$this->forumPermissions->mayHidePost($postId)) {
            throw new AccessDeniedHttpException('Not permitted');
        }

        if ($this->forumGateway->isPostHidden($postId)) {
            throw new BadRequestHttpException('Post is hidden');
        }

        $this->forumTransactions->hidePost($postId, $this->session->id(), trim($patchPostData->reason));

        return $this->respondOK();
    }

    #[OA\Delete(summary: 'Restore a hidden forum post')]
    #[Route('forum/posts/{postId}/hidden', methods: ['DELETE'], requirements: ['postId' => Requirement::POSITIVE_INT])]
    #[OA\Response(response: Response::HTTP_OK, description: 'success')]
    #[OA\Response(response: Response::HTTP_BAD_REQUEST, description: 'Post is not hidden.')]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Insufficient permissions')]
    #[OA\Response(response: Response::HTTP_NOT_FOUND, description: 'Post does not exist')]
    public function restorePost(int $postId): Response
    {
        $this->assertLoggedIn();

        $post = $this->forumGateway->getPost($postId);
        if (!$post) {
            throw new NotFoundHttpException('Post not found');
        }
        if (!$this->forumPermissions->mayRestorePost($postId)) {
            throw new AccessDeniedHttpException('Not permitted');
        }

        if (!$this->forumTransactions->restorePost($postId)) {
            throw new BadRequestHttpException('Post is not hidden');
        }

        return $this->respondOK();
    }

    // *** REACTION MANAGEMENT *** //
    // (the following endpoints are for handling reaction related actions)

    #[OA\Post(summary: 'Adds an emoji reaction to a post. An emoji is an arbitrary string but needs to be supported by the frontend.')]
    #[Route('forum/posts/{postId}/reactions/{emoji}', methods: ['POST'], requirements: ['postId' => Requirement::POSITIVE_INT])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success')]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Not permitted')]
    #[OA\Response(response: Response::HTTP_NOT_FOUND, description: 'Post does not exist')]
    public function addReaction(int $postId, string $emoji): Response
    {
        $this->assertLoggedIn();
        EmojiList::assertIsValidEmoji($emoji);

        $threadId = $this->forumGateway->getThreadForPost($postId);

        if (is_null($threadId)) {
            throw new NotFoundHttpException('Thread not found');
        }
        if (!$this->forumPermissions->mayAccessThread($threadId)) {
            throw new AccessDeniedHttpException('Not permitted');
        }

        $this->forumTransactions->addReaction($this->session->id(), $postId, $emoji);

        return $this->respondOK();
    }

    #[OA\Delete(summary: 'Remove an emoji reaction the logged in user has given from a post.')]
    #[Route('forum/posts/{postId}/reactions/{emoji}', methods: ['DELETE'], requirements: ['postId' => Requirement::POSITIVE_INT])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success')]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Not permitted')]
    #[OA\Response(response: Response::HTTP_NOT_FOUND, description: 'Post does not exist')]
    public function deleteReaction(int $postId, string $emoji): Response
    {
        $this->assertLoggedIn();
        EmojiList::assertIsValidEmoji($emoji);

        $threadId = $this->forumGateway->getThreadForPost($postId);

        if (is_null($threadId)) {
            throw new NotFoundHttpException('Thread not found');
        }
        if (!$this->forumPermissions->mayAccessThread($threadId)) {
            throw new AccessDeniedHttpException('Not permitted');
        }

        $this->forumTransactions->removeReaction($this->session->id(), $postId, $emoji);

        return $this->respondOK();
    }
}
