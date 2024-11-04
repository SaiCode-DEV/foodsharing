<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddStoreHasWallTable extends AbstractMigration
{
    /**
     * Change Method.
     *
     * Write your reversible migrations using this method.
     *
     * More information on writing migrations is available here:
     * https://book.cakephp.org/phinx/0/en/migrations.html#the-change-method
     *
     * Remember to call "create()" or "update()" and NOT "save()" when working
     * with the Table class.
     */
    public function change(): void
    {
        $this->table('fs_store_has_wallpost', [
            'id' => false,
            'primary_key' => ['store_id', 'wallpost_id'],
            'engine' => 'InnoDB',
            'encoding' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'comment' => '',
        ])
            ->addColumn('store_id', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
            ])
            ->addColumn('wallpost_id', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
                'after' => 'application_id',
            ])
            ->addIndex(['store_id'], [
                'name' => 'application_id',
                'unique' => false,
            ])
            ->addIndex(['wallpost_id'], [
                'name' => 'wallpost_id',
                'unique' => false,
            ])
            ->create();
    }
}
