<?php

use Phinx\Migration\AbstractMigration;

final class AddFoodsaverDeletedBy extends AbstractMigration
{
    public function change(): void
    {
        foreach (['fs_foodsaver', 'fs_foodsaver_archive'] as $table) {
            $this->table($table)
                ->addColumn('deleted_by', 'integer', [
                    'null' => true,
                    'default' => null,
                    'limit' => 10,
                    'signed' => false,
                    'comment' => 'id of the user who deleted this profile'
                ])
                ->addColumn('deleted_reason', 'string', [
                    'null' => true,
                    'default' => null,
                    'limit' => 200,
                    'signed' => false,
                    'collation' => 'utf8mb4_unicode_ci',
                    'encoding' => 'utf8mb4',
                    'comment' => 'optional explanation why this profile was deleted'
                ])
                ->update();
        }
    }
}
