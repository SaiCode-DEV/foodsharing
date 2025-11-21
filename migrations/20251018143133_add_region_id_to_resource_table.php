<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddRegionIdToResourceTable extends AbstractMigration
{
    public function change(): void
    {
        $this->table('fs_resource')
            ->dropForeignKey('foodsaver_id')
            ->save();

        $this->table('fs_resource')
            ->changeColumn('foodsaver_id', 'integer', [
                'null' => true,
                'limit' => 10,
                'signed' => false,
                'comment' => 'null if resource is tied to the region (commons)',
            ])
            ->addColumn('region_id', 'integer', [
                'null' => true,
                'limit' => 10,
                'signed' => false,
                'after' => 'foodsaver_id',
            ])
            ->addForeignKey('foodsaver_id', 'fs_foodsaver', 'id', ['delete' => 'CASCADE'])
            ->addIndex('region_id')
            ->addForeignKey('region_id', 'fs_bezirk', 'id', ['delete' => 'CASCADE'])
            ->update();
    }
}
