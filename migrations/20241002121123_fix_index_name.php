<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Index was created with a wrong name before, this is corrected here.
 */
final class FixIndexName extends AbstractMigration
{
    public function change(): void
    {
        $this->table('fs_store_has_wallpost')
            ->removeIndexByName('application_id')
            ->addIndex(['store_id'], [
                'name' => 'store_id',
                'unique' => false,
            ])
            ->update();
    }
}
