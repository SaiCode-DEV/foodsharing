<?php

namespace Foodsharing\Modules\Group;

use Foodsharing\Modules\Core\BaseGateway;
use Foodsharing\Modules\Core\DBConstants\Region\WorkgroupFunction;

/**
 * This gateway covers all functionality that is related to workgroups which are configured to have
 * some predefined function ({@see WorkgroupFunction}) for their attached group (region or workgroup).
 *
 * For legacy codebase reasons, some of the method names still use `region` which in this context can
 * stand for both actual regions and workgroups. Entities providing this function are always workgroups.
 */
class GroupFunctionGateway extends BaseGateway
{
    public function getRegionGroupFunctionId(int $group, int $target_id): ?int
    {
        try {
            return $this->db->fetchValueByCriteria(
                'fs_region_function',
                'function_id',
                [
                    'target_id' => $target_id,
                    'region_id' => $group,
                ]
            );
        } catch (\Exception) {
            return null;
        }
    }

    /**
     * Finds the working group in the specified region that has a certain function.
     *
     * @param int $parentId ID of the region
     * @param int $function function type, see {@see WorkgroupFunction}
     *
     * @return int|null the working group's ID or null, group with that function exists
     */
    public function getRegionFunctionGroupId(int $parentId, int $function): ?int
    {
        try {
            return $this->db->fetchValueByCriteria(
                'fs_region_function',
                'region_id',
                [
                    'target_id' => $parentId,
                    'function_id' => $function,
                ]
            );
        } catch (\Exception) {
            return null;
        }
    }

    /**
     * Adds a function to an existing working group. If the group does not exist or already has that
     * function, nothing happens.
     *
     * @param int $regionId the working group's parent region
     * @param int $targetId ID of the working group
     * @param int $functionId function type to be added, see {@see WorkgroupFunction}
     *
     * @return bool if the function was successfully added
     *
     * @throws \Exception
     */
    public function addRegionFunction(int $regionId, int $targetId, int $functionId): bool
    {
        return $this->db->insert('fs_region_function', [
            'region_id' => $regionId,
            'target_id' => $targetId,
            'function_id' => $functionId,
        ]) > 0;
    }

    /**
     * Removes a function from all working groups in a region that have the specific function. The groups
     * themselves are not altered. If no group in the region has that function, nothing happens.
     *
     * @param int $regionId ID of the region
     * @param int $functionId function type to be removed, see {@see WorkgroupFunction}
     *
     * @return int the number of groups that lost the function
     *
     * @throws \Exception
     */
    public function deleteRegionFunction(int $regionId, int $functionId): int
    {
        // TODO remove or use in REST
        return $this->db->delete('fs_region_function', [
            'region_id' => $regionId,
            'function_id' => $functionId,
        ]);
    }

    /**
     * Removes all functions from a given working group.
     *
     * @param int $targetId the group's ID
     *
     * @return int how many functions were removed from that group
     *
     * @throws \Exception
     */
    public function deleteTargetFunctions(int $targetId): int
    {
        // TODO remove or use in REST
        return $this->db->delete('fs_region_function', ['target_id' => $targetId]);
    }

    /**
     * @param int $target_id Is the Region_id of the district that the workgroup is assigned to
     * @param int|null $group_id Is the region_id of the workgroup that the functionality is assigned to
     *
     * @return bool If the function with $region_id = null is called it checks if in generall this district has a workgroup with this function
     * 				If all parameter are set it checks if this specific workgroup towards this specific district with this specific function exists.
     * 				(used in permission class)
     *
     * @throws \Exception
     */
    public function existRegionFunctionGroup(int $target_id, int $function_id, int $group_id = null): bool
    {
        try {
            if (empty($group_id)) {
                return $this->db->exists('fs_region_function', ['target_id' => $target_id, 'function_id' => $function_id]);
            } else {
                return $this->db->exists('fs_region_function', ['region_id' => $group_id, 'function_id' => $function_id, 'target_id' => $target_id]);
            }
        } catch (\Exception) {
            return false;
        }
    }

    public function isRegionFunctionGroupAdmin(int $target_id, int $function_id, int $fs_id): bool
    {
        $functionGroup = $this->getRegionFunctionGroupId($target_id, $function_id);
        if (!empty($functionGroup)) {
            $functionGroupAdminIDs = $this->getFsAdminIdsFromGroup($functionGroup);
            if (in_array($fs_id, $functionGroupAdminIDs)) {
                return true;
            }
        }

        return false;
    }

    public function getFsAdminIdsFromGroup(?int $groupId): array
    {
        return $this->db->fetchAllValuesByCriteria('fs_botschafter', 'foodsaver_id',
            ['bezirk_id' => $groupId]
        );
    }

    /**
     * Wheter the given user is admin of a given specific kind of GOALS working group.
     *
     * @param array $workgroupFunctions Working group function to check for
     * @param int $foodsaverId The users id
     * @return bool True if the user is admin of a fitting working group
     */
    public function isAdminForSpecialWorkingGroup(array $workgroupFunctions, int $foodsaverId): bool
    {
        return $this->db->fetchValue('SELECT COUNT(*)
            from fs_region_function region_function
            JOIN fs_botschafter ambassador ON ambassador.bezirk_id = region_function.region_id
            WHERE ambassador.foodsaver_id = ?
            AND region_function.function_id IN (' . implode(',', $workgroupFunctions) . ')',
            [$foodsaverId]
        ) > 0;
    }
}
