<?php

namespace Foodsharing\RestApi;

use Foodsharing\Lib\Session;
use Foodsharing\Modules\Core\DBConstants\Unit\UnitType;
use Foodsharing\Modules\Core\DBConstants\WallType;
use Foodsharing\Modules\Region\RegionGateway;
use Foodsharing\Modules\WallPost\DTO\WallPost;
use Foodsharing\Modules\WallPost\WallPostGateway;
use Foodsharing\Modules\WallPost\WallPostTransactions;
use Foodsharing\Permissions\WallPostPermissions;
use FOS\RestBundle\Controller\Annotations as Rest;
use Nelmio\ApiDocBundle\Annotation\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

#[OA\Tag(name: 'wall')]
class WallRestController extends AbstractFoodsharingRestController
{
    public function __construct(
        protected Session $session,
        private readonly WallPostGateway $wallPostGateway,
        private readonly WallPostPermissions $wallPostPermissions,
        private readonly WallPostTransactions $wallPostTransactions,
        private readonly RegionGateway $regionGateway,
    ) {
        parent::__construct($session);
    }

    #[OA\Get(summary: 'Get posts of a wall.')]
    #[Rest\Get('wall/{target}/{targetId}', requirements: ['target' => '\w+', 'targetId' => '\d+'])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success', content: new OA\JsonContent(properties: [
        new OA\Property(property: 'posts', type: 'array', items: new OA\Items(ref: new Model(type: WallPost::class))),
        new OA\Property(
            property: 'mayPost',
            description: 'Whether the user is permitted to post to this wall',
            type: 'boolean'
        ),
        new OA\Property(
            property: 'mayDelete',
            description: 'whether the user is permitted to delete all posts on this wall',
            type: 'boolean'
        ),
    ], type: 'object'))]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Not permitted to read this wall')]
    public function getPosts(
        string $target,
        int $targetId,
        #[MapQueryParameter] int $limit = 50,
        #[MapQueryParameter] int $offset = 0,
    ): Response {
        $wallType = $this->parseWallType($target, $targetId);
        if (!$this->wallPostPermissions->mayReadWall($wallType, $targetId)) {
            throw new AccessDeniedHttpException();
        }

        $posts = $this->wallPostGateway->getPosts($wallType, $targetId, $limit, $offset);
        $response = [
            'posts' => $posts,
            'mayPost' => $this->wallPostPermissions->mayWriteWall($wallType, $targetId),
            'mayDelete' => $this->wallPostPermissions->mayDeleteWall($wallType, $targetId)
        ];

        return $this->respondOK($response);
    }

    #[OA\Post(summary: 'Add a post to a wall.')]
    #[Rest\Post('wall/{target}/{targetId}', requirements: ['target' => '\w+', 'targetId' => '\d+'])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success', content: new Model(type: WallPost::class))]
    #[OA\Response(response: Response::HTTP_UNAUTHORIZED, description: 'Not logged in')]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Not permitted to post to this wall or to use the upload UUIDs')]
    #[OA\Response(response: Response::HTTP_BAD_REQUEST, description: 'Invalid post data')]
    public function addPost(string $target, int $targetId, #[MapRequestPayload] WallPost $wallPost): Response
    {
        $this->assertLoggedIn();
        $wallType = $this->parseWallType($target, $targetId);
        if (!$this->wallPostPermissions->mayWriteWall($wallType, $targetId)) {
            throw new AccessDeniedHttpException();
        }
        if (!($wallPost->body || $wallPost->pictures)) {
            throw new BadRequestHttpException('Post cannot be empty');
        }

        $post = $this->wallPostTransactions->addPost($wallPost, $wallType, $targetId);

        return $this->respondOK($post);
    }

    #[OA\Delete(summary: 'Delete a post from a wall.')]
    #[Rest\Delete('wall/{target}/{targetId}/{postId}', requirements: ['target' => '\w+', 'targetId' => '\d+', 'postId' => '\d+'])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success')]
    #[OA\Response(response: Response::HTTP_UNAUTHORIZED, description: 'Not logged in')]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Not permitted to delete this post')]
    #[OA\Response(response: Response::HTTP_NOT_FOUND, description: 'The post does not exist')]
    public function deletePost(string $target, int $targetId, int $postId): Response
    {
        $this->assertLoggedIn();
        $wallType = $this->parseWallType($target, $targetId);
        if (!$this->wallPostPermissions->mayDeleteWallPost($wallType, $targetId, $postId)) {
            throw new AccessDeniedHttpException();
        }
        if (!$this->wallPostGateway->isLinkedToTarget($postId, $wallType, $targetId)) {
            throw new NotFoundHttpException();
        }

        $this->wallPostTransactions->deletePost($postId, $wallType, $targetId);

        return $this->respondOK();
    }

    private function parseWallType(string $target, int $targetId): WallType
    {
        $wallType = WallType::tryFrom($target);
        if (!$wallType) {
            throw new BadRequestHttpException('invalid wall type');
        }

        // WallType::REGION and WallType::WORKING_GROUP use the same table and are only distinguished in the backend.
        // The frontend should always use "bezirk", meaning WallType::WORKING_GROUP.
        if ($wallType === WallType::REGION) {
            throw new BadRequestHttpException('invalid wall type');
        }
        if ($wallType === WallType::WORKING_GROUP && $this->regionGateway->getType($targetId) !== UnitType::WORKING_GROUP) {
            $wallType = WallType::REGION;
        }

        return $wallType;
    }
}
