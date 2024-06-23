<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddRegionToAchievements extends AbstractMigration
{
    public function change(): void
    {
        $this->table('fs_achievement')->addColumn('region_id', 'integer', [
            'null' => false,
            'limit' => '10',
            'signed' => false,
            'after' => 'id',
            'comment' => 'region defining the scope in which this achievement is relevant'
        ])->addForeignKey('region_id', 'fs_bezirk', 'id', [
            'delete' => 'CASCADE',
            'update' => 'CASCADE'
        ])->update();
    }
}
