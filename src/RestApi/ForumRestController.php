<?php

namespace Foodsharing\RestApi;

use Foodsharing\Lib\Session;
use Foodsharing\Modules\Foodsaver\Profile;
use Foodsharing\Modules\Region\ForumFollowerGateway;
use Foodsharing\Modules\Region\ForumGateway;
use Foodsharing\Modules\Region\ForumTransactions;
use Foodsharing\Modules\Region\RegionTransactions;
use Foodsharing\Modules\Unit\CurrentUserUnitsInterface;
use Foodsharing\Permissions\ForumPermissions;
use Foodsharing\Utility\Sanitizer;
use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\Request\ParamFetcher;
use OpenApi\Annotations as OA;
use OpenApi\Attributes as OA2;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Routing\Requirement\Requirement;

#[OA2\Tag(name: 'forum')]
#[OA2\Response(response: Response::HTTP_UNAUTHORIZED, description: 'Not logged in')]
class ForumRestController extends AbstractFoodsharingRestController
{
    public function __construct(
        protected Session $session,
        private readonly RegionTransactions $regionTransactions,
        private readonly ForumGateway $forumGateway,
        private readonly ForumFollowerGateway $forumFollowerGateway,
        private readonly ForumPermissions $forumPermissions,
        private readonly ForumTransactions $forumTransactions,
        private readonly Sanitizer $sanitizerService,
        private readonly CurrentUserUnitsInterface $currentUserUnits,
    ) {
        parent::__construct($this->session);
    }

    private function normalizeThread(array $thread): array
    {
        $normalizedThread = [
            'id' => $thread['id'],
            'regionId' => $thread['regionId'],
            'regionSubId' => $thread['regionSubId'],
            'title' => $thread['title'],
            'createdAt' => str_replace(' ', 'T', (string)$thread['time']),
            'stickiness' => $thread['sticky'] ?? 0,
            'isActive' => boolval($thread['active'] ?? true),
            'lastPost' => [
                'id' => $thread['last_post_id'],
            ],
            'creator' => [
                'id' => $thread['creator_id'],
            ],
            'status' => intval($thread['status'])
        ];
        if (isset($thread['post_time'])) {
            $normalizedThread['lastPost']['createdAt'] = str_replace(' ', 'T', (string)$thread['post_time']);
            $normalizedThread['lastPost']['body'] = $this->sanitizerService->markdownToHtml($thread['post_body']);
            $normalizedThread['lastPost']['author'] = new Profile($thread, 'foodsaver_');
        }
        if (isset($thread['creator_name'])) {
            $normalizedThread['creator'] = new Profile($thread, 'creator_');
        }

        return $normalizedThread;
    }

    /**
     * @deprecated use DTOs instead
     */
    private function normalizePost(array $post, bool $includeHiddenBody): array
    {
        return [
            'id' => $post['id'],
            'body' => ($includeHiddenBody || empty($post['hidden_reason'])) ? $post['body'] : null,
            'createdAt' => str_replace(' ', 'T', (string)$post['time']),
            'author' => new Profile($post, 'author_'),
            'reactions' => $post['reactions'] ?: new \ArrayObject(),
            'mayDelete' => $this->forumPermissions->mayDeletePost($post),
            'hidden' => $post['hidden_reason'] ? [
                'reason' => $post['hidden_reason'],
                'moderator' => new Profile($post, 'moderator_'),
                'time' => $post['hidden_time'],
            ] : null,
        ];
    }

    /**
     * Gets available threads including their last post.
     *
     * @OA\Parameter(name="forumId", in="path", @OA\Schema(type="integer"),
     *   description="which forum to return threads for (region or group)")
     * @OA\Parameter(name="forumSubId", in="path", @OA\Schema(type="integer"),
     *   description="each region/group has another namespace to separate different forums with the same base id (region/group id, here: forumId). So with any forumId, there is (currently) 2, possibly infinite, actual forums (list of threads).
     * 0: Forum, 1: Ambassador forum")
     * @OA\Parameter(name="limit", in="query", @OA\Schema(type="integer"), description="how many search results to return")
     * @OA\Parameter(name="offset", in="query", @OA\Schema(type="integer"), description="starting with which result")
     * @OA\Response(response="200", description="Success",
     *     @OA\Schema(type="object", @OA\Property(property="data", type="array", @OA\Items(type="object",
     *     @OA\Property(property="id", type="integer", description="thread id"),
     *     @OA\Property(property="regionId", type="integer", description="region/forum id"),
     *     @OA\Property(property="regionSubId", type="integer", description="region/forum sub id"),
     *     @OA\Property(property="title", type="string", description="thread title"),
     *     @OA\Property(property="createdAt", type="integer", description="region/forum sub id"),
     *     @OA\Property(property="stickiness", type="integer", description="stickiness of the thread"),
     *     @OA\Property(property="isActive", type="integer", description="region/forum sub id"),
     *     @OA\Property(property="lastPost", type="object", @OA\Items()),
     *     @OA\Property(property="creator", type="object", @OA\Items()),
     *
     * ))))
     * @OA\Response(response="403", description="Insufficient permissions to view that forum.")
     */
    #[Rest\Get('forum/{forumId}/{forumSubId}', requirements: ['forumId' => '\d+', 'forumSubId' => '\d'])]
    #[Rest\QueryParam(name: 'limit', requirements: '\d+', default: '20', description: 'how many search results to return')]
    #[Rest\QueryParam(name: 'offset', requirements: '\d+', default: '0', description: 'starting with which result')]
    public function listThreads(int $forumId, int $forumSubId, ParamFetcher $paramFetcher): Response
    {
        if (!$this->session->id()) {
            throw new UnauthorizedHttpException('');
        }
        if (!$this->forumPermissions->mayAccessForum($forumId, $forumSubId)) {
            throw new AccessDeniedHttpException();
        }

        $limit = intval($paramFetcher->get('limit'));
        $offset = intval($paramFetcher->get('offset'));

        $threads = $this->getNormalizedThreads($forumId, $forumSubId, $limit, $offset);

        $view = $this->view([
            'object' => $threads
        ], 200);

        return $this->handleView($view);
    }

    private function getNormalizedThreads(int $forumId, int $forumSubId, int $limit, int $offset): array
    {
        $threads = $this->forumGateway->listThreads($forumId, $forumSubId, $limit, $offset);
        $totalRows = $threads[0]['total_rows'] ?? 0;
        $normalizedThreads = array_map(fn ($thread) => $this->normalizeThread($thread), $threads);

        return [
            'totalRows' => $totalRows,
            'data' => $normalizedThreads,
        ];
    }

    /**
     * Get a single forum thread including some of its messages.
     *
     * @OA\Parameter(name="threadId", in="path", @OA\Schema(type="integer"),
     *   description="which ID to return threads for")
     * @OA\Response(response="200", description="Success")
     * @OA\Response(response="403", description="Insufficient permissions to view that forum/thread")
     * @OA\Response(response="404", description="Thread does not exist.")
     */
    #[Rest\Get('forum/thread/{threadId}', requirements: ['threadId' => '\d+'])]
    public function getThread(int $threadId): Response
    {
        if (!$this->session->id()) {
            throw new UnauthorizedHttpException('');
        }

        $thread = $this->forumGateway->getThread($threadId);

        if (!$thread) {
            throw new NotFoundHttpException();
        }

        if (!$this->forumPermissions->mayAccessThread($threadId)) {
            throw new AccessDeniedHttpException();
        }

        $thread = $this->normalizeThread($thread);

        $thread['isFollowingEmail'] = $this->forumFollowerGateway->isFollowingEmail($this->session->id(), $threadId);
        $thread['isFollowingBell'] = $this->forumFollowerGateway->isFollowingBell($this->session->id(), $threadId);
        $thread['mayModerate'] = $this->forumPermissions->mayModerate($threadId);
        $thread['mayHidePosts'] = $this->forumPermissions->mayHidePosts($threadId);

        $posts = $this->forumGateway->listPosts($threadId);

        $thread['posts'] = array_map(fn ($post) => $this->normalizePost($post, $thread['mayModerate']), $posts);

        $view = $this->view([
            'data' => $thread
        ], 200);

        return $this->handleView($view);
    }

    /**
     * Create a post inside a thread.
     *
     * @OA\Response(response="200", description="success")
     * @OA\Response(response="403", description="Insufficient permissions")
     */
    #[Rest\Post('forum/thread/{threadId}/posts', requirements: ['threadId' => '\d+'])]
    #[Rest\RequestParam(name: 'body', description: 'post message')]
    public function createPost(int $threadId, ParamFetcher $paramFetcher): Response
    {
        if (!$this->session->id()) {
            throw new UnauthorizedHttpException('');
        }
        if (!$this->forumPermissions->mayPostToThread($threadId)) {
            throw new AccessDeniedHttpException();
        }

        $body = $paramFetcher->get('body');
        $this->forumTransactions->addPostToThread($this->session->id(), $threadId, $body);

        return $this->handleView($this->view([], Response::HTTP_OK));
    }

    /**
     * Create a thread inside a forum.
     *
     * @OA\Response(response="200", description="success")
     * @OA\Response(response="403", description="Insufficient permissions")
     */
    #[Rest\Post('forum/{forumId}/{forumSubId}', requirements: ['forumId' => '\d+', 'forumSubId' => '\d'])]
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

        $body = $paramFetcher->get('body');
        $title = $paramFetcher->get('title');
        $sendMail = $paramFetcher->get('sendMail') ?? false;
        $regionDetails = $this->regionTransactions->getRegionDetails($forumId);
        $postActiveWithoutModeration = ($this->session->isVerified() && !$regionDetails['moderated']) || $this->currentUserUnits->isAmbassadorForRegion([$forumId]);

        $threadId = $this->forumTransactions->createThread($this->session->id(), $title, $body, $regionDetails, $forumSubId, $postActiveWithoutModeration, $sendMail);

        return $this->getThread($threadId);
    }

    /**
     * Change attributes for a thread: Stickiness, activate thread, status.
     *
     * @OA\Response(response="200", description="success")
     * @OA\Response(response="403", description="Insufficient permissions")
     */
    #[Rest\Patch('forum/thread/{threadId}', requirements: ['threadId' => '\d+'])]
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
            $this->forumGateway->setThreadTitle($threadId, $title);
        }

        return $this->getThread($threadId);
    }

    /**
     * request email notifications for activities in at thread.
     *
     * @OA\Response(response="200", description="success")
     * @OA\Response(response="403", description="Insufficient permissions")
     */
    #[Rest\Post('forum/thread/{threadId}/follow/email', requirements: ['threadId' => '\d+'])]
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
     * @OA\Response(response="200", description="success")
     * @OA\Response(response="403", description="Insufficient permissions")
     */
    #[Rest\Post('forum/thread/{threadId}/follow/bell', requirements: ['threadId' => '\d+'])]
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
     * @OA\Response(response="200", description="success")
     * @OA\Response(response="403", description="Insufficient permissions")
     */
    #[Rest\Delete('forum/thread/{threadId}/follow/email', requirements: ['threadId' => '\d+'])]
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
     * @OA\Response(response="200", description="success")
     * @OA\Response(response="403", description="Insufficient permissions")
     */
    #[Rest\Delete('forum/thread/{threadId}/follow/bell', requirements: ['threadId' => '\d+'])]
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

    /**
     * Delete a forum post.
     *
     * @OA\Response(response="200", description="success")
     * @OA\Response(response="403", description="Insufficient permissions")
     * @OA\Response(response="404", description="Post does not exist")
     */
    #[Rest\Delete('forum/post/{postId}', requirements: ['postId' => '\d+'])]
    public function deletePost(int $postId): Response
    {
        if (!$this->session->id()) {
            throw new UnauthorizedHttpException('');
        }

        $post = $this->forumGateway->getPost($postId);
        if (!$post) {
            throw new NotFoundHttpException();
        }
        if (!$this->forumPermissions->mayDeletePost($post)) {
            throw new AccessDeniedHttpException();
        }

        $this->forumTransactions->deletePostFromThread($postId, $this->session->id());

        return $this->handleView($this->view([]));
    }

    #[OA2\Patch(summary: 'Hide a forum post.')]
    #[OA2\Response(response: Response::HTTP_OK, description: 'Success')]
    #[OA2\Response(response: Response::HTTP_BAD_REQUEST, description: 'Post is already hidden.')]
    #[OA2\Response(response: Response::HTTP_FORBIDDEN, description: 'Insufficient permissions')]
    #[OA2\Response(response: Response::HTTP_NOT_FOUND, description: 'Post does not exist')]
    #[Rest\Patch('forum/post/{postId}/hide', requirements: ['postId' => Requirement::POSITIVE_INT])]
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

    #[OA2\Delete(summary: 'Restore a hidden forum post')]
    #[OA2\Response(response: Response::HTTP_OK, description: 'success')]
    #[OA2\Response(response: Response::HTTP_BAD_REQUEST, description: 'Post is not hidden.')]
    #[OA2\Response(response: Response::HTTP_FORBIDDEN, description: 'Insufficient permissions')]
    #[OA2\Response(response: Response::HTTP_NOT_FOUND, description: 'Post does not exist')]
    #[Rest\Delete('forum/post/{postId}/hide', requirements: ['postId' => Requirement::POSITIVE_INT])]
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

    /**
     * Deletes a forum thread.
     *
     * @OA\Parameter(name="threadId", in="path", @OA\Schema(type="integer"), description="ID of the thread that will be deleted")
     * @OA\Response(response="200", description="Success")
     * @OA\Response(response="403", description="Insufficient permissions to delete that thread or thread is already active")
     * @OA\Response(response="404", description="Thread does not exist.")
     */
    #[Rest\Delete('forum/thread/{threadId}', requirements: ['postId' => '\d+'])]
    public function deleteThread(int $threadId): Response
    {
        if (!$this->session->id()) {
            throw new UnauthorizedHttpException('');
        }

        $thread = $this->forumGateway->getThread($threadId);
        if (!$thread) {
            throw new NotFoundHttpException();
        }
        if (!$this->forumPermissions->mayDeleteThread($thread)) {
            throw new AccessDeniedHttpException();
        }

        $this->forumTransactions->deleteThread($threadId);

        return $this->handleView($this->view([], 200));
    }

    /**
     * Adds an emoji reaction to a post. An emoji is an arbitrary string but needs to be supported by the frontend.
     *
     * @OA\Response(response="200", description="success")
     * @OA\Response(response="403", description="Insufficient permissions")
     * @OA\Response(response="404", description="Post does not exist")
     */
    #[Rest\Post('forum/post/{postId}/reaction/{emoji}', requirements: ['postId' => '\d+', 'emoji' => '\w+'])]
    public function addReaction(int $postId, string $emoji): Response
    {
        if (!$this->session->id()) {
            throw new UnauthorizedHttpException('');
        }

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
     * @OA\Response(response="200", description="Success")
     * @OA\Response(response="403", description="Insufficient permissions")
     * @OA\Response(response="404", description="Post does not exist")
     */
    #[Rest\Delete('forum/post/{postId}/reaction/{emoji}', requirements: ['postId' => '\d+', 'emoji' => '\w+'])]
    public function deleteReaction(int $postId, string $emoji): Response
    {
        if (!$this->session->id()) {
            throw new UnauthorizedHttpException('');
        }

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

    #[OA2\Get(summary: 'Get forum following status.')]
    #[OA2\Response(response: Response::HTTP_OK, description: 'Success')]
    #[Rest\Get('forum/{regionId}/follow', requirements: ['regionId' => Requirement::POSITIVE_INT])]
    public function getIsFollowingForum(int $regionId): Response
    {
        $this->assertLoggedIn();
        $isFollowing = $this->forumFollowerGateway->isFollowingForum($regionId, $this->session->id());
        if (is_null($isFollowing)) {
            $isFollowing = $this->currentUserUnits->isAdminFor($regionId);
        }

        return $this->respondOK(['isFollowing' => $isFollowing]);
    }

    #[OA2\Patch(summary: 'Set forum following status.')]
    #[OA2\Response(response: Response::HTTP_OK, description: 'Success')]
    #[Rest\Patch('forum/{regionId}/follow', requirements: ['regionId' => Requirement::POSITIVE_INT])]
    public function setFollowingForum(int $regionId, #[MapQueryParameter] bool $isFollowing): Response
    {
        $this->assertLoggedIn();
        if (!$this->currentUserUnits->mayBezirk($regionId)) {
            throw new AccessDeniedHttpException();
        }
        $this->forumFollowerGateway->setFollowingForum($regionId, $this->session->id(), $isFollowing);

        return $this->respondOK();
    }
}
