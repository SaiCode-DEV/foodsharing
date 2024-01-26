<?php

namespace Foodsharing\RestApi;

use Foodsharing\Lib\Session;
use Foodsharing\Modules\WallPost\WallPostGateway;
use Foodsharing\Permissions\WallPostPermissions;
use FOS\RestBundle\Controller\AbstractFOSRestController;
use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\Request\ParamFetcher;
use OpenApi\Annotations as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

class WallRestController extends AbstractFOSRestController
{
    private WallPostGateway $wallPostGateway;
    private WallPostPermissions $wallPostPermissions;
    private Session $session;

    public function __construct(
        WallPostGateway $wallPostGateway,
        WallPostPermissions $wallPostPermissions,
        Session $session
    ) {
        $this->wallPostGateway = $wallPostGateway;
        $this->wallPostPermissions = $wallPostPermissions;
        $this->session = $session;
    }

    private function normalizePost(array $post): array
    {
        return [
            'id' => $post['id'],
            'body' => $post['body'],
            'createdAt' => str_replace(' ', 'T', (string)$post['time']),
            'pictures' => $post['gallery'] ?? null,
            'author' => [
                'id' => $post['foodsaver_id'],
                'name' => $post['name'],
                'avatar' => $post['photo'] ?? null
            ]
        ];
    }

    /**
     * @OA\Tag(name="wall")
     */
    #[Rest\Get('wall/{target}/{targetId}', requirements: ['targetId' => '\d+'])]
    public function getPosts(string $target, int $targetId): Response
    {
        if (!$this->session->id()) {
            throw new UnauthorizedHttpException('');
        }
        if (!$this->wallPostPermissions->mayReadWall($this->session->id(), $target, $targetId)) {
            throw new AccessDeniedHttpException();
        }

        $posts = $this->getNormalizedPosts($target, $targetId);

        $sessionId = $this->session->id();

        $view = $this->view([
            'results' => $posts,
            'mayPost' => $this->wallPostPermissions->mayWriteWall($sessionId, $target, $targetId),
            'mayDelete' => $this->wallPostPermissions->mayDeleteFromWall($sessionId, $target, $targetId)
        ], 200);

        return $this->handleView($view);
    }

    private function getNormalizedPosts(string $target, int $targetId): array
    {
        $posts = $this->wallPostGateway->getPosts($target, $targetId);

        return array_map(fn ($value) => $this->normalizePost($value), $posts);
    }

    /**
     * @OA\Tag(name="wall")
     *
     * @throws \Exception
     */
    #[Rest\Post('wall/{target}/{targetId}', requirements: ['targetId' => '\d+'])]
    #[Rest\RequestParam(name: 'body', nullable: false)]
    public function addPost(string $target, int $targetId, ParamFetcher $paramFetcher): Response
    {
        if (!$this->session->id()) {
            throw new UnauthorizedHttpException('');
        }
        if (!$this->wallPostPermissions->mayWriteWall($this->session->id(), $target, $targetId)) {
            throw new AccessDeniedHttpException();
        }

        $body = $paramFetcher->get('body');
        $postId = $this->wallPostGateway->addPost($body, $this->session->id(), $target, $targetId);

        $view = $this->view(['post' => $this->normalizePost($this->wallPostGateway->getPost($postId))], 200);

        return $this->handleView($view);
    }

    /**
     * @OA\Tag(name="wall")
     */
    #[Rest\Delete('wall/{target}/{targetId}/{id}', requirements: ['targetId' => '\d+', 'id' => '\d+'])]
    public function delPost(string $target, int $targetId, int $id): Response
    {
        if (!$this->session->id()) {
            throw new UnauthorizedHttpException('');
        }
        if (!$this->wallPostGateway->isLinkedToTarget($id, $target, $targetId)) {
            throw new AccessDeniedHttpException();
        }
        $sessionId = $this->session->id();
        if ($this->wallPostGateway->getFsByPost($id) != $sessionId
            && !$this->wallPostPermissions->mayDeleteFromWall($sessionId, $target, $targetId)
        ) {
            throw new AccessDeniedHttpException();
        }

        $this->wallPostGateway->unlinkPost($id, $target);
        $this->wallPostGateway->deletePost($id);

        $view = $this->view([], 200);

        return $this->handleView($view);
    }
}
