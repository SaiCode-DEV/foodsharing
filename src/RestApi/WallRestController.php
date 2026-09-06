<?php

namespace Foodsharing\RestApi;

use Foodsharing\Lib\Session;
use Foodsharing\Modules\Core\DBConstants\Unit\UnitType;
use Foodsharing\Modules\Core\DBConstants\WallType;
use Foodsharing\Modules\Core\Pagination;
use Foodsharing\Modules\Reaction\ReactionTransactions;
use Foodsharing\Modules\Region\RegionGateway;
use Foodsharing\Modules\WallPost\DTO\WallPost;
use Foodsharing\Modules\WallPost\EmojiList;
use Foodsharing\Modules\WallPost\WallPostGateway;
use Foodsharing\Modules\WallPost\WallPostTransactions;
use Foodsharing\Permissions\WallPostPermissions;
use Nelmio\ApiDocBundle\Annotation\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

#[OA\Tag(name: 'wall')]
#[OA\Response(response: Response::HTTP_UNAUTHORIZED, description: 'Not logged in')]
class WallRestController extends AbstractFoodsharingRestController
{
    public function __construct(
        protected Session $session,
        private readonly WallPostGateway $wallPostGateway,
        private readonly WallPostPermissions $wallPostPermissions,
        private readonly WallPostTransactions $wallPostTransactions,
        private readonly RegionGateway $regionGateway,
        private readonly ReactionTransactions $reactionTransactions,
    ) {
        parent::__construct($session);
    }

    #[OA\Get(summary: 'Get posts of a wall.')]
    #[Route('walls/{target}/{targetId}', requirements: ['target' => '\w+', 'targetId' => Requirement::POSITIVE_INT], methods: ['GET'])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success', content: new OA\JsonContent(type: 'object', properties: [
        new OA\Property(property: 'posts', type: 'array', items: new OA\Items(ref: new Model(type: WallPost::class))),
        new OA\Property(property: 'mayPost', type: 'boolean', description: 'Whether the user is permitted to post to this wall'),
        new OA\Property(property: 'mayDelete', type: 'boolean', description: 'whether the user is permitted to delete all posts on this wall'),
        new OA\Property(property: 'mayReact', type: 'boolean', description: 'whether the user is permitted to react to posts on this wall'),
    ]))]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Not permitted to read this wall')]
    #[OA\QueryParameter(name: 'anchorPostId', description: 'The ID of the post to anchor the results to. If provided, the results limit will be expanded to include that post. Ignored if the post is not linked to the wall or is behind the given offset.')]
    public function getWallPosts(string $target, int $targetId, #[MapQueryParameter] ?int $limit, #[MapQueryParameter] ?int $offset, #[MapQueryParameter] ?int $anchorPostId): Response
    {
        $wallType = $this->parseWallType($target, $targetId);
        if (!$this->wallPostPermissions->mayReadWall($wallType, $targetId)) {
            throw new AccessDeniedHttpException('Not permitted ');
        }
        $pagination = Pagination::create($limit, $offset);
        if ($anchorPostId) {
            $postIndex = $this->wallPostGateway->getPostIndex($wallType, $targetId, $anchorPostId);
            if ($postIndex && $pagination->offset + $pagination->limit <= $postIndex) {
                $pagination->limit = $postIndex - $pagination->offset;
            }
        }

        $posts = $this->wallPostGateway->getPosts($wallType, $targetId, $pagination);

        $mayReact = $this->wallPostPermissions->mayReactToPostsOnWall($wallType, $targetId);
        if ($mayReact && !empty($posts)) {
            $reactions = $this->wallPostGateway->getReactionsForPosts(array_column($posts, 'id'));
            $this->reactionTransactions->addReactionsToPosts($reactions, $posts);
        }

        return $this->respondOK([
            'posts' => $posts,
            'mayPost' => $this->wallPostPermissions->mayWriteWall($wallType, $targetId),
            'mayDelete' => $this->wallPostPermissions->mayDeleteWall($wallType, $targetId),
            'mayReact' => $mayReact,
        ]);
    }

    #[OA\Post(summary: 'Add a post to a wall.')]
    #[Route('walls/{target}/{targetId}', requirements: ['target' => '\w+', 'targetId' => Requirement::POSITIVE_INT], methods: ['POST'])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success', content: new Model(type: WallPost::class))]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Not permitted to post to this wall or to use the upload UUIDs')]
    #[OA\Response(response: Response::HTTP_BAD_REQUEST, description: 'Invalid post data')]
    public function addWallPost(string $target, int $targetId, #[MapRequestPayload] WallPost $wallPost): Response
    {
        $this->assertLoggedIn();
        $wallType = $this->parseWallType($target, $targetId);
        if (!$this->wallPostPermissions->mayWriteWall($wallType, $targetId)) {
            throw new AccessDeniedHttpException('Not permitted to post to this wall');
        }
        if (!($wallPost->body || $wallPost->pictures)) {
            throw new BadRequestHttpException('Post cannot be empty');
        }

        $post = $this->wallPostTransactions->addPost($wallPost, $wallType, $targetId);

        return $this->respondOK($post);
    }

    #[OA\Delete(summary: 'Delete a post from a wall.')]
    #[Route('walls/{target}/{targetId}/posts/{postId}', requirements: ['target' => '\w+', 'targetId' => Requirement::POSITIVE_INT, 'postId' => Requirement::POSITIVE_INT], methods: ['DELETE'])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success')]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Not permitted to delete this post')]
    #[OA\Response(response: Response::HTTP_NOT_FOUND, description: 'The post does not exist')]
    public function deleteWallPost(string $target, int $targetId, int $postId): Response
    {
        $this->assertLoggedIn();
        $wallType = $this->parseWallType($target, $targetId);
        if (!$this->wallPostGateway->isLinkedToTarget($postId, $wallType, $targetId)) {
            throw new NotFoundHttpException('The post does not exist');
        }
        if (!$this->wallPostPermissions->mayDeleteWallPost($wallType, $targetId, $postId)) {
            throw new AccessDeniedHttpException('Not permitted to delete this post');
        }

        $this->wallPostTransactions->deletePost($postId, $wallType, $targetId);

        return $this->respondOK();
    }

    #[OA\Post(summary: 'Adds a reactions to a post.', description: 'The reaction type key can be any emoji name supported by the frontend.')]
    #[Route('walls/{target}/{targetId}/posts/{postId}/reactions/{key}', requirements: ['target' => '\w+', 'targetId' => Requirement::POSITIVE_INT, 'postId' => Requirement::POSITIVE_INT, 'key' => '\w+'], methods: ['POST'])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success')]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Not permitted to react on this post')]
    #[OA\Response(response: Response::HTTP_NOT_FOUND, description: 'The post does not exist')]
    public function addWallReaction(string $target, int $targetId, int $postId, string $key): Response
    {
        $this->assertLoggedIn();
        EmojiList::assertIsValidEmoji($key);
        $wallType = $this->parseWallType($target, $targetId);
        if (!$this->wallPostGateway->isLinkedToTarget($postId, $wallType, $targetId)) {
            throw new NotFoundHttpException('The post does not exist');
        }
        if (!$this->wallPostPermissions->mayReactToPostsOnWall($wallType, $targetId)) {
            throw new AccessDeniedHttpException('Not permitted to react on this post');
        }

        $this->wallPostGateway->addReaction($postId, $this->session->id(), $key);

        return $this->respondOK();
    }

    #[OA\Delete(summary: 'Removes one of your a reactions from a post.', description: 'The reaction type key can be any emoji name supported by the frontend.')]
    #[Route('walls/{target}/{targetId}/posts/{postId}/reactions/{reactionKey}', requirements: ['target' => '\w+', 'targetId' => Requirement::POSITIVE_INT, 'postId' => Requirement::POSITIVE_INT, 'reactionKey' => '\w+'], methods: ['DELETE'])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success')]
    #[OA\Response(response: Response::HTTP_NOT_FOUND, description: 'The post does not exist')]
    public function deleteWallReaction(string $target, int $targetId, int $postId, string $reactionKey): Response
    {
        $this->assertLoggedIn();
        EmojiList::assertIsValidEmoji($reactionKey);
        $wallType = $this->parseWallType($target, $targetId);
        if (!$this->wallPostGateway->isLinkedToTarget($postId, $wallType, $targetId)) {
            throw new NotFoundHttpException('The post does not exist');
        }

        $this->wallPostGateway->removeReaction($postId, $this->session->id(), $reactionKey);

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
