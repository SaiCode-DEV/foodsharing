<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddForeignKeyToStoreMembersTable extends AbstractMigration
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
        $this->table('fs_betrieb_team')
            ->addForeignKey('betrieb_id', 'fs_betrieb', 'id', ['delete' => 'CASCADE'])
            ->addForeignKey('foodsaver_id', 'fs_foodsaver', 'id')
            ->update();
    }
}
