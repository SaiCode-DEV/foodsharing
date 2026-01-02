<?php

namespace Foodsharing\RestApi;

use Foodsharing\Lib\Session;
use Foodsharing\Modules\Content\ContentGateway;
use Foodsharing\Modules\Content\DTO\Content;
use Foodsharing\Permissions\ContentPermissions;
use Foodsharing\RestApi\Models\Content\ContentEntry;
use Nelmio\ApiDocBundle\Annotation\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

#[OA\Tag(name: 'content')]
class ContentRestController extends AbstractFoodsharingRestController
{
    public function __construct(
        private readonly ContentGateway $contentGateway,
        private readonly ContentPermissions $contentPermissions,
        protected Session $session
    ) {
        parent::__construct($this->session);
    }

    #[OA\Get(summary: 'Returns a list of all content entries.')]
    #[Route('contents', methods: ['GET'])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success', content: new OA\JsonContent(
        type: 'array',
        items: new OA\Items(ref: new Model(type: Content::class)))
    )]
    #[OA\Response(response: Response::HTTP_UNAUTHORIZED, description: 'Not logged in')]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Not permitted')]
    public function getContentList(): Response
    {
        $this->assertLoggedIn();

        if (!$this->contentPermissions->mayEditContent()) {
            throw new AccessDeniedHttpException('Not permitted');
        }

        $contentIds = $this->contentPermissions->getEditableContentIds();
        $list = $this->contentGateway->list($contentIds);

        return $this->respondOK($list);
    }

    #[OA\Get(summary: 'Returns the content entry for a specific id.')]
    #[Route('contents/{contentId}', methods: ['GET'], requirements: ['contentId' => Requirement::POSITIVE_INT])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success', content: new Model(type: Content::class))]
    #[OA\Response(response: Response::HTTP_NOT_FOUND, description: 'Content doesn\'t exist')]
    public function getContent(int $contentId): Response
    {
        $content = $this->contentGateway->getContent($contentId);
        if ($content == null) {
            throw new NotFoundHttpException('Content with the given id does not exist');
        }

        return $this->respondOK($content);
    }

    #[OA\Get(summary: 'Deletes the content entry with the specific id.')]
    #[Route('contents/{contentId}', methods: ['DELETE'], requirements: ['contentId' => Requirement::POSITIVE_INT])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success')]
    #[OA\Response(response: Response::HTTP_UNAUTHORIZED, description: 'Not logged in')]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Not permitted')]
    #[OA\Response(response: Response::HTTP_NOT_FOUND, description: 'Content doesn\'t exist')]
    public function deleteContent(int $contentId): Response
    {
        $this->assertLoggedIn();

        if (is_null($this->contentGateway->getContent($contentId))) {
            throw new NotFoundHttpException('Content with the given id does not exist');
        }

        if (!$this->contentPermissions->mayEditContentId($contentId)) {
            throw new AccessDeniedHttpException('Not permitted to delete this content');
        }

        $this->contentGateway->delete($contentId);

        return $this->respondOK();
    }

    #[OA\Patch(summary: 'Updates the content entry with the specific id.')]
    #[Route('contents/{contentId}', methods: ['PATCH'], requirements: ['contentId' => Requirement::POSITIVE_INT])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success')]
    #[OA\Response(response: Response::HTTP_UNAUTHORIZED, description: 'Not logged in')]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Not permitted')]
    #[OA\Response(response: Response::HTTP_NOT_FOUND, description: 'Content doesn\'t exist')]
    public function editContent(int $contentId, #[MapRequestPayload] ContentEntry $content): Response
    {
        $this->assertLoggedIn();

        if (is_null($this->contentGateway->getContent($contentId))) {
            throw new NotFoundHttpException('Content with the given id does not exist');
        }

        if (!$this->contentPermissions->mayEditContentId($contentId)) {
            throw new AccessDeniedHttpException('Not permitted to edit this content');
        }

        $this->contentGateway->update($contentId, $content);

        return $this->respondOK();
    }

    #[OA\Post(summary: 'Adds a new content entry')]
    #[Route('contents', methods: ['POST'])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success', content: new OA\JsonContent(type: 'object', properties: [
        new OA\Property(property: 'id', type: 'integer', description: 'Id of the newly created content')
    ]))]
    #[OA\Response(response: Response::HTTP_UNAUTHORIZED, description: 'Not logged in')]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Not permitted')]
    #[OA\Response(response: Response::HTTP_NOT_FOUND, description: 'Content doesn\'t exist')]
    public function addContent(#[MapRequestPayload] ContentEntry $content): Response
    {
        $this->assertLoggedIn();
        if (!$this->contentPermissions->mayCreateContent()) {
            throw new AccessDeniedHttpException('Not permitted to add content entries');
        }

        $id = $this->contentGateway->create($content);

        return $this->respondOK(['id' => $id]);
    }
}
