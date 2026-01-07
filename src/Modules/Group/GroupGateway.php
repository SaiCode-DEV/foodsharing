<?php

namespace Foodsharing\Modules\Group;

use Foodsharing\Modules\Core\BaseGateway;
use Foodsharing\Modules\Core\Database;
use Foodsharing\Modules\Core\DatabaseNoValueFoundException;

/* Group gateway meant to collect queries common for regions as well as working groups */
class GroupGateway extends BaseGateway
{
    public function __construct(
        Database $db,
    ) {
        parent::__construct($db);
    }

    /**
     * Returns the mailbox name, i.e. the email address without the domain part, for a region or working group.
     *
     * @throws DatabaseNoValueFoundException if the group does not exist
     */
    public function getGroupMailName(int $groupId): ?string
    {
        return $this->db->fetchValue('
			SELECT		mb.`name`
			FROM		`fs_bezirk` bz
			INNER JOIN	`fs_mailbox` mb
			ON			bz.`mailbox_id` = mb.`id`
			WHERE		bz.`id` = :bezirk_id
		', [':bezirk_id' => $groupId]);
    }

    public function deleteGroup($groupId)
    {
        $parent_id = $this->db->fetchValueByCriteria(
            'fs_bezirk',
            'parent_id',
            ['id' => $groupId]
        );

        $this->db->update(
            'fs_foodsaver',
            ['bezirk_id' => null],
            ['bezirk_id' => $groupId]
        );
        $this->db->update(
            'fs_bezirk',
            ['parent_id' => 0],
            ['parent_id' => $groupId]
        );

        $this->db->delete('fs_bezirk', ['id' => $groupId]);

        $count = $this->db->count('fs_bezirk', ['parent_id' => $parent_id]);

        if ($count == 0) {
            $this->db->update(
                'fs_bezirk',
                ['has_children' => 0],
                ['id' => $parent_id]
            );
        }
    }

    /**
     * Returns whether the group contains any subregions or working groups.
     */
    public function hasSubregions(int $groupId): bool
    {
        return $this->db->exists('fs_bezirk', [
            'parent_id' => $groupId
        ]);
    }

    /**
     * Returns whether the group contains any stores. This does not include subregions.
     */
    public function hasStores(int $groupId): bool
    {
        return $this->db->exists('fs_betrieb', [
            'bezirk_id' => $groupId
        ]);
    }

    /**
     * Returns whether the group contains any foodsharepoints. This does not search subregions, if the group has
     * any, but includes FSPs that have not been accepted yet.
     */
    public function hasFoodSharePoints(int $groupId): bool
    {
        return $this->db->exists('fs_fairteiler', [
            'bezirk_id' => $groupId
        ]);
    }
}
