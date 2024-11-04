<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddColumnsForHidingForumPosts extends AbstractMigration
{
    public function change(): void
    {
        $this->table('fs_theme_post')
            ->addColumn('hidden_time', 'datetime')
            ->addColumn('hidden_by', 'integer', [
                'length' => 10,
                'signed' => false,
            ])
            ->addForeignKey('hidden_by', 'fs_foodsaver', 'id', ['delete' => 'NO_ACTION', 'update' => 'CASCADE'])
            ->addColumn('hidden_reason', 'string')
            ->update();
    }
}
