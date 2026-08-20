<?php

namespace Foodsharing\Modules\ResourceMosaic;

use Foodsharing\Modules\Bell\BellTransactions;
use Foodsharing\Modules\Bell\DTO\Bell;
use Foodsharing\Modules\Core\DBConstants\Bell\BellType;
use Foodsharing\Modules\Core\DBConstants\Region\WorkgroupFunction;
use Foodsharing\Modules\Core\DBConstants\Uploads\UploadUsage;
use Foodsharing\Modules\Foodsaver\FoodsaverGateway;
use Foodsharing\Modules\Region\RegionGateway;
use Foodsharing\Modules\Region\RegionTransactions;
use Foodsharing\Modules\ResourceMosaic\DTO\Resource;
use Foodsharing\Modules\ResourceMosaic\DTO\ResourceForDisplay;
use Foodsharing\Modules\Uploads\UploadsGateway;
use Foodsharing\Modules\Uploads\UploadsTransactions;

class ResourceTransactions
{
    public function __construct(
        private readonly ResourceGateway $resourceGateway,
        private readonly UploadsGateway $uploadsGateway,
        private readonly UploadsTransactions $uploadsTransactions,
        private readonly FoodsaverGateway $foodsaverGateway,
        private readonly RegionTransactions $regionTransactions,
        private readonly BellTransactions $bellTransactions,
        private readonly RegionGateway $regionGateway,
    ) {
    }

    /**
     * Adds a resource including categories.
     */
    public function addResource(?int $userId, Resource $resource): ResourceForDisplay
    {
        $resource->images = array_map(fn ($picture) => $this->uploadsTransactions->fixUUIDForWriting($picture), $resource->images);
        $resourceId = $this->resourceGateway->insertResource($userId, $resource);
        $this->resourceGateway->setResourceCategories($resourceId, $resource->categories);
        $this->tagImages($resourceId, $resource->images);
        $resource = $this->resourceGateway->getResource($resourceId);
        if (!is_null($userId)) {
            $bellData = $this->getGroupedBellEventData($resource);
            if (!is_null($bellData)) {
                $this->bellTransactions->addGroupedBellEvent(...$bellData);
            }
        }

        return $resource;
    }

    /**
     * Edits a resource including categories.
     */
    public function editResource(int $resourceId, Resource $resource): void
    {
        // remove no longer used images
        $existingImages = $this->resourceGateway->getResource($resourceId)->images;
        $uuidsToDelete = array_diff($existingImages, $resource->images);
        foreach ($uuidsToDelete as $uuid) {
            $this->uploadsTransactions->deleteUploadedFile(substr($uuid, 13));
        }

        $this->tagImages($resourceId, $resource->images);

        $resource->images = array_map(fn ($picture) => $this->uploadsTransactions->fixUUIDForWriting($picture), $resource->images);
        $this->resourceGateway->editResource($resourceId, $resource);
        $this->resourceGateway->setResourceCategories($resourceId, $resource->categories);
    }

    public function deleteResource(int $resourceId): void
    {
        // remove bell
        $resource = $this->resourceGateway->getResource($resourceId);
        if (!is_null($resource->user)) {
            $bellData = $this->getGroupedBellEventData($resource);
            if (!is_null($bellData)) {
                $this->bellTransactions->removeGroupedBellEvent(...$bellData);
            }
        }

        // remove images
        $existingImages = $this->resourceGateway->getResource($resourceId)->images;
        foreach ($existingImages as $uuid) {
            $this->uploadsTransactions->deleteUploadedFile(substr($uuid, 13));
        }

        // delete resource
        $this->resourceGateway->deleteResource($resourceId);
    }

    private function tagImages(int $resourceId, array $images): void
    {
        $uuids = array_map(fn ($image) => substr($image, 13), $images);
        $this->uploadsGateway->setUsage($uuids, UploadUsage::RESOURCE, $resourceId);
    }

    private function getGroupedBellEventData(ResourceForDisplay $resource): ?array
    {
        $homeRegion = $this->foodsaverGateway->getHomeRegionOfFoodsaver($resource->user->id);
        if (!$homeRegion || $resource->isPrivate) {
            return null;
        }

        $regionName = $this->regionGateway->getRegionName($homeRegion);
        $bellRecipients = $this->regionTransactions->getResponsibleUsersForFunction($homeRegion, WorkgroupFunction::RESOURCES);
        $bellRecipients = array_column($bellRecipients, 'id');
        $bellRecipients = array_diff($bellRecipients, [$resource->user->id]);
        $bell = Bell::create(
            'new_resource_title',
            'new_resource',
            'fas fa-shapes',
            ['href' => '/region?bid=' . $homeRegion . '&sub=resources&newSinceId=' . $resource->id . '&resourceId=' . $resource->id], [
                'region' => $regionName,
                'user' => $resource->user->name,
                'title' => $resource->name,
            ],
            BellType::createIdentifier(BellType::NEW_RESOURCE, $homeRegion)
        );

        return [$bellRecipients, $bell, $resource->id];
    }

    /**
     * @return ResourceForDisplay[]
     */
    public function getResourcesForRegion(int $regionId, int $foodsaverId): array
    {
        $userResources = $this->resourceGateway->getUserResourcesForRegion($regionId, $foodsaverId);
        $commonsResources = $this->resourceGateway->getCommonsResourcesForRegion($regionId, $foodsaverId);

        return array_merge($userResources, $commonsResources);
    }
}
