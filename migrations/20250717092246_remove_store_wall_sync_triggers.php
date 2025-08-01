<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Removes the trigger that were added in 20240827093249_add_store_wall_sync_triggers.
 */
final class RemoveStoreWallSyncTriggers extends AbstractMigration
{
    public function change(): void
    {
        $this->execute('DROP TRIGGER sync_after_insert_fs_betrieb_notiz');
        $this->execute('DROP TRIGGER sync_after_insert_fs_store_has_wallpost');
        $this->execute('DROP TRIGGER sync_after_delete_fs_betrieb_notiz');
        $this->execute('DROP TRIGGER sync_after_delete_fs_store_has_wallpost');
    }
}
