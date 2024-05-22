<?php

use Phinx\Migration\AbstractMigration;

final class AddFoodsaverNoAutomaticDelete extends AbstractMigration
{
    public function change(): void
    {
        foreach (['fs_foodsaver', 'fs_foodsaver_archive'] as $table) {
            $this->table($table)
                ->addColumn('no_automatic_delete', 'boolean', [
                    'null' => false,
                    'default' => false,
                    'comment' => 'If true user is not automatically deleted'
                ])
                ->update();
        }
    }
}
