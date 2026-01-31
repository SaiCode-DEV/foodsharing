<?php

namespace Foodsharing\RestApi;

use Foodsharing\Lib\Session;
use Foodsharing\Modules\Blog\BlogGateway;
use Foodsharing\Modules\Blog\BlogTransactions;
use Foodsharing\Modules\Blog\DTO\BlogPost;
use Foodsharing\Modules\Core\PaginatedBlogPosts;
use Foodsharing\Modules\Core\Pagination;
use Foodsharing\Permissions\BlogPermissions;
use Foodsharing\RestApi\Models\Blog\BlogPostData;
use Nelmio\ApiDocBundle\Annotation\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

#[OA\Tag(name: 'blog')]
class BlogpostRestController extends AbstractFoodsharingRestController
{
    public function __construct(
        private readonly BlogGateway $blogGateway,
        private readonly BlogTransactions $blogTransactions,
        private readonly BlogPermissions $blogPermissions,
        protected Session $session,
    ) {
        parent::__construct($session);
    }

    #[OA\Get(summary: 'Returns blog posts.')]
    #[Route('blog', methods: ['GET'])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success', content: new Model(type: PaginatedBlogPosts::class))]
    public function getBlogposts(#[MapQueryParameter] ?int $limit, #[MapQueryParameter] ?int $offset): Response
    {
        $pagination = Pagination::create($limit, $offset, 10);

        $posts = $this->blogGateway->listNews($pagination);

        return $this->respondOK($posts);
    }

    #[OA\Get(summary: 'Returns a specific blog post.')]
    #[Route('blog/{blogPostId}', methods: ['GET'], requirements: ['blogPostId' => Requirement::POSITIVE_INT])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success', content: new Model(type: BlogPost::class))]
    #[OA\Response(response: Response::HTTP_NOT_FOUND, description: 'Blog post not found')]
    public function getBlogpost(int $blogPostId): Response
    {
        $blogPost = $this->blogGateway->getPost($blogPostId);

        if (is_null($blogPost)) {
            throw new NotFoundHttpException('Blog post not found');
        }

        return $this->respondOK($blogPost);
    }

    #[OA\Patch(summary: 'Edit a specific blog post.')]
    #[Route(path: 'blog/{blogId}', methods: ['PATCH'], requirements: ['blogId' => Requirement::POSITIVE_INT])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success')]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Not permitted')]
    #[OA\Response(response: Response::HTTP_UNAUTHORIZED, description: 'Not logged in')]
    #[OA\Response(response: Response::HTTP_NOT_FOUND, description: 'Blog post not found')]
    public function editBlogPost(int $blogId, #[MapRequestPayload] BlogPostData $post): Response
    {
        $this->assertLoggedIn();

        $post->id = $blogId;
        $authorId = $this->blogGateway->getPostAuthor($blogId);
        if ($authorId === false) {
            throw new NotFoundHttpException('Blog post not found.');
        }

        if (!$this->blogPermissions->mayEdit($blogId)) {
            throw new AccessDeniedHttpException('You do not have permission to edit this blog post.');
        }

        if (!$this->blogPermissions->mayPublish($blogId)) {
            throw new AccessDeniedHttpException('You do not have permission to change the published state of this blog post.');
        }

        if (isset($post->isPublished) && !empty($post->isPublished)) {
            $this->blogGateway->setPublished($blogId, $post->isPublished);
        }

        $this->blogTransactions->editBlogPost($authorId, $post);

        return $this->respondOK();
    }

    #[OA\Put(summary: 'Sets a specific blog posts published status.')]
    #[Route('blog/{blogId}/published', methods: ['PUT'], requirements: ['blogId' => Requirement::POSITIVE_INT])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success')]
    #[OA\Response(response: Response::HTTP_UNAUTHORIZED, description: 'Not logged in')]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Insufficient permissions')]
    #[OA\Response(response: Response::HTTP_NOT_FOUND, description: 'Blog post not found')]
    public function setBlogpostPublished(int $blogId, #[MapQueryParameter] bool $isPublished): Response
    {
        $this->assertLoggedIn();

        $author = $this->blogGateway->getPostAuthor($blogId);
        if ($author === false) {
            throw new NotFoundHttpException('Blogpost not found.');
        }

        if (!$this->blogPermissions->mayPublish($blogId)) {
            throw new AccessDeniedHttpException('Not permitted');
        }

        $this->blogGateway->setPublished($blogId, $isPublished);

        return $this->respondOK();
    }

    #[OA\Delete(summary: 'Removes a blogpost.')]
    #[Route('blog/{blogId}', methods: ['DELETE'], requirements: ['blogId' => Requirement::POSITIVE_INT])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success')]
    #[OA\Response(response: Response::HTTP_UNAUTHORIZED, description: 'Not logged in')]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Insufficient permissions')]
    #[OA\Response(response: Response::HTTP_NOT_FOUND, description: 'Blog post not found')]
    public function removeBlogpost(int $blogId): Response
    {
        $this->assertLoggedIn();

        $post = $this->blogGateway->getPost($blogId, false);
        if (is_null($post)) {
            throw new NotFoundHttpException('Blogpost not found.');
        }
        if (!$this->blogPermissions->mayDelete($blogId)) {
            throw new AccessDeniedHttpException('Not permitted');
        }

        $this->blogTransactions->deleteBlogPost($post);

        return $this->respondOK();
    }

    #[OA\Post(summary: 'Adds a new blog post', description: 'The post will be publicly visible immediately.')]
    #[Route('blog', methods: ['POST'])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success', content: new Model(type: BlogPost::class))]
    #[OA\Response(response: Response::HTTP_UNAUTHORIZED, description: 'Not logged in')]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Not permitted')]
    public function addBlogpost(#[MapRequestPayload] BlogPostData $post): Response
    {
        $this->assertLoggedIn();

        if (!$this->blogPermissions->mayAdd()) {
            throw new AccessDeniedHttpException('Not permitted');
        }

        if (empty($post->isPublished)) {
            $post->isPublished = true;
        }

        $postId = $this->blogTransactions->addBlogPost($post);
        $post = $this->blogGateway->getPost($postId, false);

        return $this->respondOK($post);
    }
}
