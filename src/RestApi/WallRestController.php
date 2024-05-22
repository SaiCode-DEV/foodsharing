<?php

namespace Foodsharing\RestApi;

use Foodsharing\Lib\Session;
use Foodsharing\Modules\WallPost\DTO\WallPost;
use Foodsharing\Modules\WallPost\WallPostGateway;
use Foodsharing\Modules\WallPost\WallPostTransactions;
use Foodsharing\Permissions\WallPostPermissions;
use FOS\RestBundle\Controller\Annotations as Rest;
use Nelmio\ApiDocBundle\Annotation\Model;
use OpenApi\Attributes as OA;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[OA\Tag(name: 'wall')]
class WallRestController extends AbstractFoodsharingRestController
{
    public function __construct(
        protected Session $session,
        private readonly WallPostGateway $wallPostGateway,
        private readonly WallPostPermissions $wallPostPermissions,
        private readonly WallPostTransactions $wallPostTransactions,
    ) {
    }

    /**
     * Get posts of a wall.
     */
    #[Rest\Get('wall/{target}/{targetId}', requirements: ['target' => '\w+', 'targetId' => '\d+'])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success', content: new OA\JsonContent(type: 'object', properties: [
        new OA\Property(property: 'posts', type: 'array', items: new OA\Items(ref: new Model(type: WallPost::class))),
        new OA\Property(property: 'mayPost', type: 'boolean', description: 'Whether the user is permitted to post to this wall'),
        new OA\Property(property: 'mayDelete', type: 'boolean', description: 'whether the user is permitted to delete all posts on this wall'),
    ]))]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Not permitted to read this wall')]
    public function getPosts(string $target, int $targetId): Response
    {
        if (!$this->wallPostPermissions->mayReadWall($target, $targetId)) {
            throw new AccessDeniedHttpException();
        }

        $posts = $this->wallPostGateway->getPosts($target, $targetId);
        $response = [
            'posts' => $posts,
            'mayPost' => $this->wallPostPermissions->mayWriteWall($target, $targetId),
            'mayDelete' => $this->wallPostPermissions->mayDeleteWall($target, $targetId)
        ];

        return $this->handleView($this->view($response, Response::HTTP_OK));
    }

    /**
     * Add a post to a wall.
     */
    #[Rest\Post('wall/{target}/{targetId}', requirements: ['target' => '\w+', 'targetId' => '\d+'])]
    #[OA\RequestBody(content: new Model(type: WallPost::class),
        description: 'Only properties `body` and `pictures` are used to create the new post, others may be ommitted.')]
    #[ParamConverter('wallPost', class: 'Foodsharing\Modules\WallPost\DTO\WallPost', converter: 'fos_rest.request_body')]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success', content: new Model(type: WallPost::class))]
    #[OA\Response(response: Response::HTTP_UNAUTHORIZED, description: 'Not logged in')]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Not permitted to post to this wall')]
    #[OA\Response(response: Response::HTTP_BAD_REQUEST, description: 'Invalid post data')]
    public function addPost(string $target, int $targetId, WallPost $wallPost, ValidatorInterface $validator): Response
    {
        $this->assertLoggedIn();
        if (!$this->wallPostPermissions->mayWriteWall($target, $targetId)) {
            throw new AccessDeniedHttpException();
        }
        $errors = $validator->validate($wallPost);
        if ($errors->count() > 0) {
            $firstError = $errors->get(0);
            throw new BadRequestHttpException(json_encode(['field' => $firstError->getPropertyPath(), 'message' => $firstError->getMessage()]));
        }
        if (!($wallPost->body || $wallPost->pictures)) {
            throw new BadRequestHttpException('Post cannot be empty');
        }

        $post = $this->wallPostTransactions->addPost($wallPost, $target, $targetId);

        return $this->handleView($this->view($post, Response::HTTP_OK));
    }

    /**
     * Delete a post from a wall.
     */
    #[Rest\Delete('wall/{target}/{targetId}/{postId}', requirements: ['target' => '\w+', 'targetId' => '\d+', 'postId' => '\d+'])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success')]
    #[OA\Response(response: Response::HTTP_UNAUTHORIZED, description: 'Not logged in')]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Not permitted to delete this post')]
    #[OA\Response(response: Response::HTTP_NOT_FOUND, description: 'The post does not exist')]
    public function deletePost(string $target, int $targetId, int $postId): Response
    {
        $this->assertLoggedIn();
        if (!$this->wallPostPermissions->mayDeleteWallPost($target, $targetId, $postId)) {
            throw new AccessDeniedHttpException();
        }
        if (!$this->wallPostGateway->isLinkedToTarget($postId, $target, $targetId)) {
            throw new NotFoundHttpException();
        }

        $this->wallPostTransactions->deletePost($postId, $target, $targetId);

        return $this->handleView($this->view(null, Response::HTTP_OK));
    }
}
