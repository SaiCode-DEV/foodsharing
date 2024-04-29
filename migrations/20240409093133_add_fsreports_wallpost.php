<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddFsreportsWallpost extends AbstractMigration
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
        $this->table('fs_fsreports_has_wallpost', [
            'id' => false,
            'primary_key' => ['fsreports_id', 'wallpost_id'],
            'engine' => 'InnoDB',
            'encoding' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'comment' => 'This wall is over ALL reports of a foodsaver',
        ])
            ->addColumn('fsreports_id', 'integer', [
                'null' => false,
                'limit' => '10',
                'signed' => false,
                'comment' => 'foodsaver Id that has all reports',
            ])
            ->addColumn('wallpost_id', 'integer', [
                'null' => false,
                'limit' => '10',
                'signed' => false,
                'comment' => 'wallpost_id',
            ])
            ->addIndex(['fsreports_id'], [
                'name' => 'foodsaver_ID',
                'unique' => false,
            ])
            ->addIndex(['wallpost_id'], [
                'name' => 'wallpost_id',
                'unique' => false,
            ])
            ->addForeignKey('fsreports_id', 'fs_foodsaver', 'id')
            ->addForeignKey('wallpost_id', 'fs_wallpost', 'id', [
                'delete' => 'CASCADE',
            ])
            ->create();
    }
}
