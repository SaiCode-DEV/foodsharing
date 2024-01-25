<?php

namespace Foodsharing\RestApi;

use Foodsharing\Lib\Session;
use Foodsharing\Modules\Content\ContentGateway;
use Foodsharing\Modules\Content\DTO\Content;
use Foodsharing\Permissions\ContentPermissions;
use FOS\RestBundle\Controller\AbstractFOSRestController;
use FOS\RestBundle\Controller\Annotations as Rest;
use Nelmio\ApiDocBundle\Annotation\Model;
use OpenApi\Annotations as OA;
use OpenApi\Attributes as OA2;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

class ContentRestController extends AbstractFOSRestController
{
    public function __construct(
        private readonly ContentGateway $contentGateway,
        private readonly ContentPermissions $contentPermissions,
        private readonly Session $session
    ) {
    }

    /**
     * Returns a list of all content entries.
     */
    #[OA2\Tag(name: 'content')]
    #[Rest\Get('content')]
    #[OA2\Response(response: '200', description: 'Success', content: new OA2\JsonContent(
        type: 'array',
        items: new OA2\Items(ref: new Model(type: Content::class)))
    )]
    #[OA2\Response(response: '401', description: 'Not logged in')]
    #[OA2\Response(response: '403', description: 'Insufficient permissions')]
    public function getContentList(): Response
    {
        if (!$this->session->id()) {
            throw new UnauthorizedHttpException('');
        }

        if (!$this->contentPermissions->mayEditContent()) {
            throw new AccessDeniedHttpException();
        }

        $contentIds = $this->contentPermissions->getEditableContentIds();
        $list = $this->contentGateway->list($contentIds);

        return $this->handleView($this->view($list, 200));
    }

    /**
     * Returns the content entry for a specific id.
     *
     * @OA\Response(response="200", description="Success", @Model(type=Content::class))
     * @OA\Response(response="401", description="Not logged in")
     * @OA\Response(response="404", description="Content id does not exist")
     * @OA\Tag(name="content")
     */
    #[Rest\Get('content/{contentId}', requirements: ['contentId' => '\d+', 'status' => '[0-1]'])]
    public function getContent(int $contentId): Response
    {
        if (!$this->session->id()) {
            throw new UnauthorizedHttpException('');
        }

        $content = $this->contentGateway->getContent($contentId);
        if ($content == null) {
            throw new NotFoundHttpException('content id does not exist');
        }

        return $this->handleView($this->view($content, 200));
    }

    /**
     * Deletes the content entry with the specific id.
     */
    #[OA2\Tag(name: 'content')]
    #[Rest\Delete('content/{contentId}')]
    #[OA2\Response(response: '200', description: 'Success')]
    #[OA2\Response(response: '401', description: 'Not logged in')]
    #[OA2\Response(response: '403', description: 'Insufficient permissions')]
    public function deleteContent(int $contentId): Response
    {
        if (!$this->session->id()) {
            throw new UnauthorizedHttpException('');
        }

        if (!$this->contentPermissions->mayEditContentId($contentId)) {
            throw new AccessDeniedHttpException();
        }

        $this->contentGateway->delete($contentId);

        return $this->handleView($this->view([], 200));
    }
}
