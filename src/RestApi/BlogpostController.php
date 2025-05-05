<?php

namespace Foodsharing\RestApi;

use Foodsharing\Lib\Session;
use Foodsharing\Modules\Blog\BlogGateway;
use Foodsharing\Modules\Blog\BlogTransactions;
use Foodsharing\Modules\Blog\DTO\BlogPost;
use Foodsharing\Modules\Blog\DTO\BlogPostList;
use Foodsharing\Permissions\BlogPermissions;
use Foodsharing\RestApi\Models\Blog\BlogPostData;
use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\Request\ParamFetcher;
use Nelmio\ApiDocBundle\Annotation\Model;
use OpenApi\Annotations as OA;
use OpenApi\Attributes as OA2;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class BlogpostController extends AbstractFoodsharingRestController
{
    public function __construct(
        private readonly BlogGateway $blogGateway,
        private readonly BlogTransactions $blogTransactions,
        private readonly BlogPermissions $blogPermissions,
        protected Session $session,
    ) {
        parent::__construct($session);
    }

    #[OA2\Get(summary: 'Returns a page from the list of blog posts. The page can be empty if the page number is too large.')]
    #[OA2\Tag(name: 'blog')]
    #[OA2\Response(
        response: Response::HTTP_OK,
        description: 'Successful',
        content: new OA2\JsonContent(ref: new Model(type: BlogPostList::class))
    )]
    #[Rest\Get('blog')]
    #[Rest\QueryParam(name: 'page', requirements: '\d+', default: 0, description: 'Which page of updates to return')]
    public function getBlogposts(ParamFetcher $paramFetcher): Response
    {
        $page = intval($paramFetcher->get('page'));

        $posts = $this->blogGateway->listNews($page);

        return $this->handleView($this->view($posts, 200));
    }

    /**
     * Returns a specific blog post.
     *
     * @OA\Parameter(name="blogPostId", in="path", @OA\Schema(type="integer"), description="which post to return")
     * @OA\Response(response="200", description="Success.", @Model(type=BlogPost::class))
     * @OA\Response(response="404", description="Blog post not found.")
     * @OA\Tag(name="blog")
     */
    #[Rest\Get('blog/{blogPostId}', requirements: ['blogPostId' => Requirement::POSITIVE_INT])]
    public function getBlogpost(int $blogPostId): Response
    {
        $blogPost = $this->blogGateway->getPost($blogPostId);

        if (is_null($blogPost)) {
            throw new NotFoundHttpException('Blog post not found');
        }

        return $this->handleView($this->view($blogPost, 200));
    }

    #[OA2\Tag('blog')]
    #[Rest\Patch(path: 'blog/{blogId}', requirements: ['blogId' => Requirement::POSITIVE_INT])]
    #[OA2\Parameter(name: 'blogId', in: 'path', required: true, schema: new OA2\Schema(type: 'integer'))]
    #[OA2\Response(response: Response::HTTP_OK, description: 'Successful')]
    #[OA2\Response(response: Response::HTTP_FORBIDDEN, description: 'Forbidden')]
    #[OA2\Response(response: Response::HTTP_UNAUTHORIZED, description: 'Not logged in')]
    #[OA2\Response(response: Response::HTTP_NOT_FOUND, description: 'Blog post not found')]
    #[OA2\RequestBody(content: new Model(type: BlogPostData::class))]
    #[ParamConverter(data: 'post', class: BlogPostData::class, converter: 'fos_rest.request_body')]
    public function editBlogPost(int $blogId, BlogPostData $post, ValidatorInterface $validator): Response
    {
        $this->assertLoggedIn();

        $this->assertThereAreNoValidationErrors($validator, $post);

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

    #[OA2\Get(summary: 'Publishes or depublishes a blog post.')]
    #[OA2\Tag('blog')]
    #[Rest\Patch('blog/{blogId}/publish', requirements: ['blogId' => Requirement::POSITIVE_INT])]
    #[OA2\Parameter(name: 'blogId', in: 'path', required: true, schema: new OA2\Schema(type: 'integer'))]
    #[Rest\RequestParam(name: 'isPublished', description: 'New published state', strict: true)]
    #[OA2\Response(response: Response::HTTP_OK, description: 'Successful')]
    #[OA2\Response(response: Response::HTTP_UNAUTHORIZED, description: 'Not logged in')]
    #[OA2\Response(response: Response::HTTP_FORBIDDEN, description: 'Insufficient permissions')]
    #[OA2\Response(response: Response::HTTP_NOT_FOUND, description: 'Blog post not found')]
    public function setBlogpostPublished(int $blogId, ParamFetcher $paramFetcher): Response
    {
        $sessionId = $this->session->id();
        if (!$sessionId) {
            throw new UnauthorizedHttpException('', 'Not logged in.');
        }

        $author = $this->blogGateway->getPostAuthor($blogId);
        if ($author === false) {
            throw new NotFoundHttpException('Blogpost not found.');
        }

        if (!$this->blogPermissions->mayPublish($blogId)) {
            throw new AccessDeniedHttpException();
        }

        if (!is_bool($paramFetcher->get('isPublished'))) {
            throw new \InvalidArgumentException('isPublished must be a boolean');
        }

        $this->blogGateway->setPublished($blogId, $paramFetcher->get('isPublished'));

        return $this->respondOK();
    }

    /**
     * Removes one blogpost from the database.
     *
     * @OA\Parameter(name="blogId", in="path", @OA\Schema(type="integer"), description="which post to delete")
     */
    #[OA2\Tag('blog')]
    #[Rest\Delete('blog/{blogId}', requirements: ['blogId' => Requirement::POSITIVE_INT])]
    #[OA2\Response(response: Response::HTTP_OK, description: 'Successful')]
    #[OA2\Response(response: Response::HTTP_UNAUTHORIZED, description: 'Not logged in')]
    #[OA2\Response(response: Response::HTTP_FORBIDDEN, description: 'Insufficient permissions')]
    #[OA2\Response(response: Response::HTTP_NOT_FOUND, description: 'Blog post not found')]
    public function removeBlogpost(int $blogId): Response
    {
        $this->assertLoggedIn();

        $post = $this->blogGateway->getPost($blogId, false);
        if (is_null($post)) {
            throw new NotFoundHttpException('Blogpost not found.');
        }
        if (!$this->blogPermissions->mayDelete($blogId)) {
            throw new AccessDeniedHttpException();
        }

        $this->blogTransactions->deleteBlogPost($post);

        return $this->handleView($this->view([], 200));
    }

    #[OA2\Post(summary: 'Publishes a new blog post. The post will be publicly visible immediately.')]
    #[OA2\Tag(name: 'blog')]
    #[OA2\Response(
        response: Response::HTTP_OK,
        description: 'Success',
        content: new OA2\JsonContent(ref: new Model(type: BlogPost::class))
    )]
    #[OA2\Response(response: Response::HTTP_BAD_REQUEST, description: 'Invalid data')]
    #[OA2\Response(response: Response::HTTP_UNAUTHORIZED, description: 'Not logged in')]
    #[OA2\Response(response: Response::HTTP_FORBIDDEN, description: 'Insufficient permissions')]
    #[Rest\Post('blog')]
    #[OA2\RequestBody(content: new Model(type: BlogPostData::class))]
    #[ParamConverter(data: 'post', class: BlogPostData::class, converter: 'fos_rest.request_body')]
    public function addBlogpost(BlogPostData $post, ValidatorInterface $validator): Response
    {
        $this->assertLoggedIn();

        if (!$this->blogPermissions->mayAdd()) {
            throw new AccessDeniedHttpException();
        }
        $this->assertThereAreNoValidationErrors($validator, $post);

        if (empty($post->isPublished)) {
            $post->isPublished = true;
        }

        $postId = $this->blogTransactions->addBlogPost($post);

        return $this->handleView($this->view($this->blogGateway->getPost($postId, false), 200));
    }
}
