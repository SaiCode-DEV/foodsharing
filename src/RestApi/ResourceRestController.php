<?php

namespace Foodsharing\RestApi;

use Foodsharing\Lib\Session;
use Foodsharing\Modules\Categories\ResourceCategoriesGateway;
use Foodsharing\Modules\Core\DatabaseNoValueFoundException;
use Foodsharing\Modules\ResourceMosaic\DTO\Resource;
use Foodsharing\Modules\ResourceMosaic\DTO\ResourceForDisplay;
use Foodsharing\Modules\ResourceMosaic\ResourceGateway;
use Foodsharing\Modules\ResourceMosaic\ResourceTransactions;
use Foodsharing\Modules\Store\DTO\CommonLabel;
use Foodsharing\Permissions\ResourcePermissions;
use FOS\RestBundle\Controller\Annotations as Rest;
use Nelmio\ApiDocBundle\Annotation\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Requirement\Requirement;

#[OA\Tag(name: 'resource')]
#[OA\Response(response: Response::HTTP_UNAUTHORIZED, description: 'Not logged in')]
#[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Not permitted')]
class ResourceRestController extends AbstractFoodsharingRestController
{
    public function __construct(
        protected Session $session,
        private readonly ResourceGateway $resourceGateway,
        private readonly ResourcePermissions $resourcePermissions,
        private readonly ResourceTransactions $resourceTransactions,
        private readonly ResourceCategoriesGateway $resourceCategoriesGateway,
    ) {
        parent::__construct($session);
    }

    #[OA\Get(summary: 'Get the list of resource categories.')]
    #[Rest\Get('resources/categories')]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success', content: new OA\JsonContent(
        type: 'array',
        description: 'The list of resource categories.',
        items: new OA\Items(ref: new Model(type: CommonLabel::class)),
    ))]
    public function getResourceCategories(): Response
    {
        $categories = $this->resourceCategoriesGateway->getCategories();

        return $this->respondOK($categories);
    }

    #[OA\Get(summary: 'Get the list of resources available in a given region.')]
    #[Rest\Get('region/{regionId}/resources', requirements: ['regionId' => Requirement::POSITIVE_INT])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success', content: new OA\JsonContent(
        type: 'array',
        description: 'The list of resources available in the given region.',
        items: new OA\Items(ref: new Model(type: ResourceForDisplay::class)),
    ))]
    public function getResourcesForRegion(int $regionId): Response
    {
        $this->assertLoggedIn();
        if (!$this->resourcePermissions->maySeeResources($regionId)) {
            throw new AccessDeniedHttpException();
        }
        $resources = $this->resourceGateway->getResourcesForRegion($regionId, $this->session->id());

        return $this->respondOK($resources);
    }

    #[OA\Post(summary: 'Add a new resource for the logged in user.')]
    #[Rest\Post('resources')]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success', content: new Model(type: ResourceForDisplay::class))]
    public function addOwnResource(#[MapRequestPayload] Resource $resource): Response
    {
        $this->assertLoggedIn();
        if (!$this->resourcePermissions->mayAddResource()) {
            throw new AccessDeniedHttpException();
        }

        $resource = $this->resourceTransactions->addResource($this->session->id(), $resource);

        return $this->respondOK($resource);
    }

    #[OA\Delete(summary: 'Delete a resource.')]
    #[Rest\Delete('resources/{resourceId}', requirements: ['resourceId' => Requirement::POSITIVE_INT])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success')]
    #[OA\Response(response: Response::HTTP_NOT_FOUND, description: 'The resource does not exist')]
    public function deleteResource(int $resourceId): Response
    {
        $this->assertLoggedIn();
        try {
            $ownerId = $this->resourceGateway->getResourceOwner($resourceId);
        } catch (DatabaseNoValueFoundException $e) {
            throw new NotFoundHttpException();
        }
        if (!$this->resourcePermissions->mayDeleteResource($ownerId)) {
            throw new AccessDeniedHttpException();
        }

        $this->resourceTransactions->deleteResource($resourceId);

        return $this->respondOK();
    }

    #[OA\Patch(summary: 'Edit a resource.')]
    #[Rest\Patch('resources/{resourceId}', requirements: ['resourceId' => Requirement::POSITIVE_INT])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success', content: new Model(type: ResourceForDisplay::class))]
    #[OA\Response(response: Response::HTTP_NOT_FOUND, description: 'The resource does not exist')]
    public function editResource(int $resourceId, #[MapRequestPayload] Resource $resource): Response
    {
        $this->assertLoggedIn();
        try {
            $ownerId = $this->resourceGateway->getResourceOwner($resourceId);
        } catch (DatabaseNoValueFoundException $e) {
            throw new NotFoundHttpException();
        }
        if (!$this->resourcePermissions->mayEditResource($ownerId)) {
            throw new AccessDeniedHttpException();
        }

        $this->resourceTransactions->editResource($resourceId, $resource);
        $resource = $this->resourceGateway->getResource($resourceId);

        return $this->respondOK($resource);
    }

    #[OA\Post(summary: 'Favorite a resource.')]
    #[Rest\Post('resources/{resourceId}/favorite', requirements: ['resourceId' => Requirement::POSITIVE_INT])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success')]
    public function favoriteResource(int $resourceId): Response
    {
        $this->assertLoggedIn();
        // permission check would be useless, since no harm can be done.
        $this->resourceGateway->favoriteResource($this->session->id(), $resourceId);

        return $this->respondOK();
    }

    #[OA\Delete(summary: 'Unfavorite a resource.')]
    #[Rest\Delete('resources/{resourceId}/favorite', requirements: ['resourceId' => Requirement::POSITIVE_INT])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success')]
    public function unfavoriteResource(int $resourceId): Response
    {
        $this->assertLoggedIn();
        $this->resourceGateway->unfavoriteResource($this->session->id(), $resourceId);

        return $this->respondOK();
    }
}
