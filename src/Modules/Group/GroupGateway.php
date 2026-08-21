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
        $mailboxId = $this->db->fetchValueByCriteria(
            'fs_bezirk',
            'mailbox_id',
            ['id' => $groupId]
        );

        // one transaction: an abort must not leave a half-deleted region
        $this->db->beginTransaction();
        try {
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
            $this->db->execute('
                DELETE t FROM fs_theme t
                JOIN fs_bezirk_has_theme ht
    			ON ht.theme_id = t.id
                WHERE ht.bezirk_id = :regionId
    		', [
                ':regionId' => $groupId,
            ]);
            $this->db->execute('
                DELETE p FROM fs_wallpost p
                JOIN fs_bezirk_has_wallpost hp
    			ON hp.wallpost_id = p.id
                WHERE hp.bezirk_id = :regionId
    		', [
                ':regionId' => $groupId,
            ]);

            // the fs_event foreign key only nulls bezirk_id; the wall posts go first
            // because only their link rows cascade with the events
            $this->db->execute('
                DELETE p FROM fs_wallpost p
                JOIN fs_event_has_wallpost hp
    			ON hp.wallpost_id = p.id
                JOIN fs_event e
    			ON e.id = hp.event_id
                WHERE e.bezirk_id = :regionId
    		', [
                ':regionId' => $groupId,
            ]);
            $this->db->delete('fs_event', ['bezirk_id' => $groupId]);

            $this->db->delete('fs_bezirk', ['id' => $groupId]);

            // the mailbox is only referenced from fs_bezirk and has to go separately;
            // its messages cascade with it
            if ($mailboxId) {
                $this->db->delete('fs_mailbox', ['id' => $mailboxId]);
            }

            $count = $this->db->count('fs_bezirk', ['parent_id' => $parent_id]);

            if ($count == 0) {
                $this->db->update(
                    'fs_bezirk',
                    ['has_children' => 0],
                    ['id' => $parent_id]
                );
            }

            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Returns whether the group's mailbox still contains emails.
     */
    public function hasMailboxEmails(int $groupId): bool
    {
        $mailboxId = $this->db->fetchValueByCriteria('fs_bezirk', 'mailbox_id', ['id' => $groupId]);
        if (!$mailboxId) {
            return false;
        }

        return $this->db->exists('fs_mailbox_message', ['mailbox_id' => $mailboxId]);
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
