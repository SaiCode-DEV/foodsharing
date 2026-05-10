<?php

namespace Foodsharing\Modules\Unit;

/**
 * The interface provides access to all unit based parts like working groups and regions for the current user.
 */
interface CurrentUserUnitsInterface
{
    /**
     * Returns the home region id of current user.
     *
     * @return int|null home region identifier of user the user exists (Not set home region is the value 0)
     */
    public function getCurrentRegionId(): ?int;

    /**
     * Returns an array of regions of user, with array of following information.
     *
     * - id
     * - name
     * - type
     * - parent_id
     * - hasAchievements.
     */
    public function getRegions(): array;

    /**
     * Returns a list of region Ids where the user is member of.
     *
     * @return int[]
     */
    public function listRegionIDs(): array;

    /**
     * Returns a list of region ids where the user is ambassador.
     *
     * @param bool $includeWorkingGroups allows to filter out working group regions (currently unused)
     * @return int[]
     */
    public function getMyAmbassadorRegionIds(bool $includeWorkingGroups = true): array;

    /**
     * Checks if user is in at least one region ambassador.
     *
     * @deprecated Could be replaced by @see isAdminFor()
     */
    public function isAmbassador(): bool;

    /**
     * Checks is user is ambassador for a special region.
     *
     * @param int|null $regionId to check or `null`for overall check
     *
     * @return bool ambassador state of user
     */
    public function isAdminFor(?int $regionId): bool;

    /**
     * Check if user have permission to acces region.
     *
     * @param int $regionId Identifier of region
     *
     * @return bool true if user is member of region or has at least orga permission
     */
    public function mayBezirk(int $regionId): bool;

    /**
     * Checks if the current user is an ambassador for one of the regions in the list of region IDs.
     *
     * @param int[] $regionIds Array of regions to check
     * @param bool $include_groups Check although working groups
     * @param bool $include_parent_regions although check if user is admin of parent region
     *
     * @return bool true if current user is ambassador
     */
    public function isAmbassadorForRegion($regionIds, $include_groups = true, $include_parent_regions = false): bool;

    /**
     * Clears the cached units information in session so the user's regions/home-region are reloaded on next access.
     */
    public function clearUnitsInformation(): void;
}
