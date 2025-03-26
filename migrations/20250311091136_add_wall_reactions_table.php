<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddWallReactionsTable extends AbstractMigration
{
    public function change(): void
    {
        $this->table('fs_wall_post_reaction', ['id' => false])
            ->addColumn('post_id', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
            ])
            ->addColumn('foodsaver_id', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
            ])
            ->addColumn('key', 'string', [
                'null' => false,
                'limit' => 63,
            ])
            ->addColumn('time', 'datetime', ['null' => false])

            ->addForeignKey('post_id', 'fs_wallpost', 'id', [
                'delete' => 'CASCADE',
                'update' => 'CASCADE'
            ])
            ->addForeignKey('foodsaver_id', 'fs_foodsaver', 'id', [
                'delete' => 'CASCADE',
                'update' => 'CASCADE'
            ])
            ->changePrimaryKey(['post_id', 'foodsaver_id', 'key'])

            ->addIndex(['post_id', 'foodsaver_id', 'key'], ['unique' => true])
            ->addIndex(['post_id'], ['unique' => false])
            ->create();
    }
}
