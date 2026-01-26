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
use Nelmio\ApiDocBundle\Annotation\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
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
    #[Route('resources/categories', methods: ['GET'])]
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
    #[Route('regions/{regionId}/resources', methods: ['GET'], requirements: ['regionId' => Requirement::POSITIVE_INT])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success', content: new OA\JsonContent(
        type: 'array',
        description: 'The list of resources available in the given region.',
        items: new OA\Items(ref: new Model(type: ResourceForDisplay::class)),
    ))]
    public function getResourcesForRegion(int $regionId): Response
    {
        $this->assertLoggedIn();
        if (!$this->resourcePermissions->maySeeResources($regionId)) {
            throw new AccessDeniedHttpException('Not permitted');
        }
        $resources = $this->resourceTransactions->getResourcesForRegion($regionId, $this->session->id());

        return $this->respondOK($resources);
    }

    #[OA\Get(summary: 'Get the list of resources of the logged in user')]
    #[Route('resources/own', methods: ['GET'])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success', content: new OA\JsonContent(
        type: 'array',
        description: 'The list of resources provided by the logged in user.',
        items: new OA\Items(ref: new Model(type: ResourceForDisplay::class)),
    ))]
    public function getOwnResources(): Response
    {
        $this->assertLoggedIn();
        $resources = $this->resourceGateway->getResourcesByUserId($this->session->id());

        return $this->respondOK($resources);
    }

    #[OA\Post(summary: 'Add a new resource for the logged in user.')]
    #[Route('resources', methods: ['POST'])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success', content: new Model(type: ResourceForDisplay::class))]
    public function addOwnResource(#[MapRequestPayload] Resource $resource): Response
    {
        $this->assertLoggedIn();
        if (!$this->resourcePermissions->mayAddResource()) {
            throw new AccessDeniedHttpException('You are not allowed to add resources');
        }
        if (!$this->resourcePermissions->mayRestrictResourceToRegion($resource->regionId)) {
            throw new AccessDeniedHttpException('You are not allowed to restrict the resource to the given region');
        }

        $resource = $this->resourceTransactions->addResource($this->session->id(), $resource);

        return $this->respondOK($resource);
    }

    #[OA\Post(summary: 'Add a new commons resource for the given region.')]
    #[Route('resources/commons', methods: ['POST'])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success', content: new Model(type: ResourceForDisplay::class))]
    public function addCommonsResource(#[MapRequestPayload] Resource $resource): Response
    {
        $this->assertLoggedIn();
        if (!$resource->regionId) {
            throw new BadRequestHttpException('Region id must be set for commons resources');
        }
        if (!$this->resourcePermissions->mayEditCommonsResourcesInRegion($resource->regionId)) {
            throw new AccessDeniedHttpException('You are not allowed to add commons resources in the given region');
        }

        $resource = $this->resourceTransactions->addResource(null, $resource);

        return $this->respondOK($resource);
    }

    #[OA\Delete(summary: 'Delete a resource.')]
    #[Route('resources/{resourceId}', methods: ['DELETE'], requirements: ['resourceId' => Requirement::POSITIVE_INT])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success')]
    #[OA\Response(response: Response::HTTP_NOT_FOUND, description: 'The resource does not exist')]
    public function deleteResource(int $resourceId): Response
    {
        $this->assertLoggedIn();

        try {
            $ownerId = $this->resourceGateway->getResourceOwner($resourceId);
        } catch (DatabaseNoValueFoundException $e) {
            throw new NotFoundHttpException('The resource does not exist');
        }
        if (is_null($ownerId)) {
            $resource = $this->resourceGateway->getResource($resourceId);
            if (!$this->resourcePermissions->mayEditCommonsResourcesInRegion($resource->regionId)) {
                throw new AccessDeniedHttpException('You are not allowed to edit this resource');
            }
        } else {
            if (!$this->resourcePermissions->mayDeleteResource($ownerId)) {
                throw new AccessDeniedHttpException('You are not allowed to edit this resource');
            }
        }

        $this->resourceTransactions->deleteResource($resourceId);

        return $this->respondOK();
    }

    #[OA\Patch(summary: 'Edit a resource.')]
    #[Route('resources/{resourceId}', methods: ['PATCH'], requirements: ['resourceId' => Requirement::POSITIVE_INT])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success', content: new Model(type: ResourceForDisplay::class))]
    #[OA\Response(response: Response::HTTP_NOT_FOUND, description: 'The resource does not exist')]
    public function editResource(int $resourceId, #[MapRequestPayload] Resource $resource): Response
    {
        $this->assertLoggedIn();
        try {
            $ownerId = $this->resourceGateway->getResourceOwner($resourceId);
        } catch (DatabaseNoValueFoundException $e) {
            throw new NotFoundHttpException('The resource does not exist');
        }
        if (is_null($ownerId) ?
            !$this->resourcePermissions->mayEditCommonsResourcesInRegion($resource->regionId) :
            !$this->resourcePermissions->mayEditResource($ownerId)
        ) {
            throw new AccessDeniedHttpException('You are not allowed to edit this resource');
        }
        if (!$this->resourcePermissions->mayRestrictResourceToRegion($resource->regionId)) {
            throw new AccessDeniedHttpException('You are not allowed to restrict the resource to the given region');
        }

        $this->resourceTransactions->editResource($resourceId, $resource);
        $resource = $this->resourceGateway->getResource($resourceId);

        return $this->respondOK($resource);
    }

    #[OA\Post(summary: 'Favorite a resource.')]
    #[Route('resources/{resourceId}/favorite', methods: ['POST'], requirements: ['resourceId' => Requirement::POSITIVE_INT])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success')]
    public function favoriteResource(int $resourceId): Response
    {
        $this->assertLoggedIn();
        // permission check would be useless, since no harm can be done.
        $this->resourceGateway->favoriteResource($this->session->id(), $resourceId);

        return $this->respondOK();
    }

    #[OA\Delete(summary: 'Unfavorite a resource.')]
    #[Route('resources/{resourceId}/favorite', methods: ['DELETE'], requirements: ['resourceId' => Requirement::POSITIVE_INT])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success')]
    public function unfavoriteResource(int $resourceId): Response
    {
        $this->assertLoggedIn();
        $this->resourceGateway->unfavoriteResource($this->session->id(), $resourceId);

        return $this->respondOK();
    }

    #[OA\Get(summary: 'Get the current users permissions related to resources in a given region.')]
    #[Route('regions/{regionId}/resources/permissions', methods: ['GET'], requirements: ['regionId' => Requirement::POSITIVE_INT])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success', content: new OA\JsonContent(type: 'object', properties: [
        new OA\Property(property: 'mayEditCommonsResourcesInRegion', type: 'boolean'),
    ]))]
    public function getResourcePermissionsForRegion(int $regionId): Response
    {
        $this->assertLoggedIn();
        if (!$this->resourcePermissions->maySeeResources($regionId)) {
            throw new AccessDeniedHttpException('Not permitted');
        }

        $permissions = [
            'mayEditCommonsResourcesInRegion' => $this->resourcePermissions->mayEditCommonsResourcesInRegion($regionId),
        ];

        return $this->respondOK($permissions);
    }
}
