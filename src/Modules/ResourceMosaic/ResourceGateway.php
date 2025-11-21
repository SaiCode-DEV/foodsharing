<?php

namespace Foodsharing\Modules\ResourceMosaic;

use DateTime;
use Foodsharing\Modules\Core\BaseGateway;
use Foodsharing\Modules\Core\Database;
use Foodsharing\Modules\Foodsaver\Profile;
use Foodsharing\Modules\ResourceMosaic\DTO\Resource;
use Foodsharing\Modules\ResourceMosaic\DTO\ResourceForDisplay;

class ResourceGateway extends BaseGateway
{
    public function __construct(Database $db)
    {
        parent::__construct($db);
    }

    /**
     * Returns all user-owned resources a user can see in a region.
     * This does not include commons resources.
     *
     * @param int $regionId the ID of the region to get resources for
     * @param int $foodsaverId the ID of the user viewing the resources
     * @return ResourceForDisplay[]
     */
    public function getUserResourcesForRegion(int $regionId, int $foodsaverId): array
    {
        $resources = $this->db->fetchAll('SELECT
                r.*,
                GROUP_CONCAT(rhc.category_id) AS categories,
                fs.name AS foodsaver_name,
                fs.photo AS foodsaver_photo,
                fs.is_sleeping AS foodsaver_is_sleeping,
                fs.bezirk_id AS home_region_id,
                fs.last_login,
                fr.resource_id IS NOT NULL AS is_favorite
            FROM `fs_resource` r
            JOIN `fs_foodsaver` fs ON fs.id = r.foodsaver_id  
            JOIN `fs_foodsaver_has_bezirk` fhr ON fhr.foodsaver_id = r.foodsaver_id
            LEFT OUTER JOIN `fs_resource_has_category` rhc ON rhc.resource_id = r.id
            LEFT OUTER JOIN `fs_foodsaver_has_favorite_resource` fr ON fr.resource_id = r.id AND fr.foodsaver_id = :foodsaverId1
            LEFT OUTER JOIN `fs_bezirk_closure` rc ON rc.bezirk_id = fhr.bezirk_id AND rc.ancestor_id = r.region_id
            WHERE fhr.bezirk_id = :regionId
            AND (
                r.foodsaver_id = :foodsaverId2 OR
                r.is_private = 0
                OR EXISTS (
                SELECT 1
                FROM fs_buddy b
                WHERE
                    b.confirmed = 1 AND
                    b.foodsaver_id = :foodsaverId3 AND
                    b.buddy_id = r.foodsaver_id
                )
            )
            AND (rc.bezirk_id IS NOT NULL OR r.region_id IS NULL)
            GROUP BY r.id', [
                'regionId' => $regionId,
                'foodsaverId1' => $foodsaverId,
                'foodsaverId2' => $foodsaverId,
                'foodsaverId3' => $foodsaverId,
            ]);

        return array_map(function ($row) use ($regionId) {
            return $this->createResourceForDisplay($row, $regionId);
        }, $resources);
    }

    /**
     * Returns all commons resources for a region.
     *
     * @param int $regionId the ID of the region to get resources for
     * @param int $foodsaverId the ID of the user viewing the resources
     * @return ResourceForDisplay[]
     */
    public function getCommonsResourcesForRegion(int $regionId, int $foodsaverId): array
    {
        $resources = $this->db->fetchAll('SELECT
                r.*,
                GROUP_CONCAT(rhc.category_id) AS categories,
                fr.resource_id IS NOT NULL AS is_favorite
            FROM `fs_resource` r
            JOIN `fs_bezirk_closure` rc ON rc.ancestor_id = r.region_id	
            LEFT OUTER JOIN `fs_resource_has_category` rhc ON rhc.resource_id = r.id
            LEFT OUTER JOIN `fs_foodsaver_has_favorite_resource` fr ON fr.resource_id = r.id AND fr.foodsaver_id = :foodsaverId
            WHERE :regionId = rc.bezirk_id
            AND r.foodsaver_id IS NULL
            GROUP BY r.id', [
                'regionId' => $regionId,
                'foodsaverId' => $foodsaverId,
            ]);

        return array_map(function ($row) use ($regionId) {
            return $this->createResourceForDisplay($row, $regionId);
        }, $resources);
    }

    /**
     * @return ResourceForDisplay[]
     */
    public function getResourcesByUserId(int $userId): array
    {
        $resources = $this->db->fetchAll('SELECT
                r.*,
                GROUP_CONCAT(rhc.category_id) AS categories,
                fs.name AS foodsaver_name,
                fs.photo AS foodsaver_photo,
                fs.is_sleeping AS foodsaver_is_sleeping,
                fs.bezirk_id AS home_region_id,
                fs.last_login,
                0 AS is_favorite
            FROM `fs_resource` r
            JOIN `fs_foodsaver` fs ON fs.id = r.foodsaver_id
            LEFT OUTER JOIN `fs_resource_has_category` rhc ON rhc.resource_id = r.id
            WHERE fs.id = ?
            GROUP BY r.id
            ORDER BY r.id ASC', [$userId]);

        return array_map(function ($row) {
            return $this->createResourceForDisplay($row);
        }, $resources);
    }

    public function getResource(int $resourceId): ?ResourceForDisplay
    {
        $resource = $this->db->fetch('SELECT
                r.*,
                GROUP_CONCAT(rhc.category_id) AS categories,
                fs.name AS foodsaver_name,
                fs.photo AS foodsaver_photo,
                fs.is_sleeping AS foodsaver_is_sleeping,
                fs.bezirk_id AS home_region_id,
                fs.last_login,
                fr.resource_id IS NOT NULL AS is_favorite
            FROM `fs_resource` r
            LEFT OUTER JOIN `fs_foodsaver` fs ON fs.id = r.foodsaver_id  
            LEFT OUTER JOIN `fs_resource_has_category` rhc ON rhc.resource_id = r.id
            LEFT OUTER JOIN `fs_foodsaver_has_favorite_resource` fr ON fr.resource_id = r.id
            WHERE r.id = :resourceId', [
                'resourceId' => $resourceId,
            ]);

        return $this->createResourceForDisplay($resource);
    }

    private function createResourceForDisplay(array $data, int $regionId = 0): ResourceForDisplay
    {
        $resource = new ResourceForDisplay();
        $resource->id = $data['id'];
        $resource->name = $data['name'];
        $resource->description = $data['description'];
        $resource->categories = $data['categories'] !== null ? array_map('intval', explode(',', $data['categories'])) : [];
        $resource->regionId = $data['region_id'];
        if (!is_null($data['foodsaver_id'])) {
            // User resources
            $resource->user = new Profile($data, 'foodsaver_');
            $resource->isHomeRegion = $data['home_region_id'] == $regionId;
            $resource->isUserActive = (new DateTime())->diff(new DateTime($data['last_login']))->days <= 30 && !$resource->user->isSleeping;
        } else {
            // Commons resources
            $resource->user = null;
            $resource->isHomeRegion = $resource->regionId === $regionId;
            $resource->isUserActive = true;
        }
        $resource->isPrivate = boolval($data['is_private']);
        $resource->isFavorite = boolval($data['is_favorite']);
        $resource->openness = $data['openness'];
        $resource->images = $data['images'] !== null ? explode(',', $data['images']) : [];

        return $resource;
    }

    /**
     * Inserts a new resource.
     * Doesn't set the resource categories.
     *
     * @see ResourceTransactions::addResource to add a resource and set categories.
     * @return int the id of the added resource
     */
    public function insertResource(?int $userId, Resource $resource): int
    {
        return $this->db->insert('fs_resource', [
            'name' => $resource->name,
            'description' => $resource->description,
            'foodsaver_id' => $userId,
            'is_private' => $resource->isPrivate,
            'openness' => $resource->openness,
            'images' => count($resource->images) ? implode(',', $resource->images) : null,
            'region_id' => $resource->regionId,
        ]);
    }

    /**
     * Sets the categories for a resource.
     *
     * This will overwrite any existing categories for the resource.
     *
     * @param int $resourceId the ID of the resource to set categories for
     * @param int[] $categories the IDs of the categories to set for the resource
     */
    public function setResourceCategories(int $resourceId, array $categories): void
    {
        $this->db->delete('fs_resource_has_category', ['resource_id' => $resourceId]);
        $this->db->insertMultiple('fs_resource_has_category', array_map(
            fn ($category) => ['resource_id' => $resourceId, 'category_id' => $category],
            $categories,
        ));
    }

    public function getResourceOwner(int $resourceId): ?int
    {
        return $this->db->fetchValueById('fs_resource', 'foodsaver_id', $resourceId);
    }

    public function deleteResource(int $resourceId): void
    {
        $this->db->delete('fs_resource', ['id' => $resourceId]);
    }

    /**
     * Edits a resource. Doesn't set the resource categories.
     */
    public function editResource(int $resourceId, Resource $resource): void
    {
        $this->db->update('fs_resource', [
            'name' => $resource->name,
            'description' => $resource->description,
            'is_private' => $resource->isPrivate,
            'openness' => $resource->openness,
            'images' => count($resource->images) ? implode(',', $resource->images) : null,
            'region_id' => $resource->regionId,
        ], ['id' => $resourceId]);
    }

    public function getUserResourceCount(int $userId): int
    {
        return $this->db->count('fs_resource', ['foodsaver_id' => $userId]);
    }

    public function favoriteResource(int $foodsaverId, int $resourceId): void
    {
        $this->db->insertIgnore('fs_foodsaver_has_favorite_resource', [
            'foodsaver_id' => $foodsaverId,
            'resource_id' => $resourceId,
        ]);
    }

    public function unfavoriteResource(int $foodsaverId, int $resourceId): void
    {
        $this->db->delete('fs_foodsaver_has_favorite_resource', [
            'foodsaver_id' => $foodsaverId,
            'resource_id' => $resourceId,
        ]);
    }
}
