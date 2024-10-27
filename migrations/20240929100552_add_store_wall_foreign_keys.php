<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddStoreWallForeignKeys extends AbstractMigration
{
    public function change(): void
    {
        $this->table('fs_store_has_wallpost')
            ->addForeignKey('store_id', 'fs_betrieb', 'id', ['delete' => 'CASCADE'])
            ->addForeignKey('wallpost_id', 'fs_wallpost', 'id', ['delete' => 'CASCADE'])
            ->update();
    }
}
