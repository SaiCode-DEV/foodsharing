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
    public function getBlogpostsAction(ParamFetcher $paramFetcher): Response
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
    #[Rest\Get('blog/{blogPostId}', requirements: ['blogPostId' => '\d+'])]
    public function getBlogpost(int $blogPostId): Response
    {
        $blogPost = $this->blogGateway->getPost($blogPostId);

        if (is_null($blogPost)) {
            throw new NotFoundHttpException('Blog post not found');
        }

        return $this->handleView($this->view($blogPost, 200));
    }

    /**
     * Publishes (isPublished=1) or depublishes (isPublished=0) a blogpost.
     *
     * @OA\Parameter(name="blogId", in="path", @OA\Schema(type="integer"), description="which post to (de)publish")
     * @OA\Response(response="200", description="Success.")
     * @OA\Response(response="401", description="Not logged in.")
     * @OA\Response(response="403", description="Insufficient permissions to manage this blogpost.")
     * @OA\Response(response="404", description="Blogpost not found.")
     * @OA\Tag(name="blog")
     */
    #[Rest\Patch('blog/{blogId}', requirements: ['blogId' => '\d+'])]
    #[Rest\RequestParam(name: 'isPublished', requirements: '(0|1)')]
    public function setBlogpostPublished(int $blogId, ParamFetcher $paramFetcher): Response
    {
        $sessionId = $this->session->id();
        if (!$sessionId) {
            throw new UnauthorizedHttpException('', 'Not logged in.');
        }

        $author = $this->blogGateway->getAuthorOfPost($blogId);
        if ($author === false) {
            throw new NotFoundHttpException('Blogpost not found.');
        }
        if (!$this->blogPermissions->mayPublish($blogId)) {
            throw new AccessDeniedHttpException();
        }

        $newPublishedState = boolval($paramFetcher->get('isPublished'));
        $this->blogGateway->setPublished($blogId, $newPublishedState);

        return $this->handleView($this->view([], 200));
    }

    /**
     * Removes one blogpost from the database.
     *
     * @OA\Parameter(name="blogId", in="path", @OA\Schema(type="integer"), description="which post to delete")
     * @OA\Response(response="200", description="Success.")
     * @OA\Response(response="401", description="Not logged in.")
     * @OA\Response(response="403", description="Insufficient permissions to remove this blogpost.")
     * @OA\Response(response="404", description="Blogpost not found.")
     * @OA\Tag(name="blog")
     */
    #[Rest\Delete('blog/{blogId}', requirements: ['blogId' => '\d+'])]
    public function removeBlogpost(int $blogId): Response
    {
        $sessionId = $this->session->id();
        if (!$sessionId) {
            throw new UnauthorizedHttpException('', 'Not logged in.');
        }

        $author = $this->blogGateway->getAuthorOfPost($blogId);
        if ($author === false) {
            throw new NotFoundHttpException('Blogpost not found.');
        }
        if (!$this->blogPermissions->mayDelete($blogId)) {
            throw new AccessDeniedHttpException();
        }

        $this->blogGateway->del_blog_entry($blogId);

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
    #[ParamConverter(data: 'post', class: 'Foodsharing\RestApi\Models\Blog\BlogPostData', converter: 'fos_rest.request_body')]
    public function addBlogpost(BlogPostData $post, ValidatorInterface $validator): Response
    {
        $this->assertLoggedIn();
        if (!$this->blogPermissions->mayAdd()) {
            throw new AccessDeniedHttpException();
        }
        $this->assertThereAreNoValidationErrors($validator, $post);

        $postId = $this->blogTransactions->addBlogPost($post);

        return $this->handleView($this->view($this->blogGateway->getPost($postId), 200));
    }
}
