<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddStoreCategoryForeignKey extends AbstractMigration
{
    /**
     * Adds a foreign key between fs_betrieb and fs_betrieb_kategorie.
     */
    public function change(): void
    {
        $this->execute('ALTER TABLE `fs_betrieb` CHANGE `betrieb_kategorie_id` `betrieb_kategorie_id` INT(10) UNSIGNED NULL DEFAULT NULL; ');

        $this->table('fs_betrieb')
            ->addForeignKey('betrieb_kategorie_id', 'fs_betrieb_kategorie', 'id', [
                'delete' => 'SET_NULL',
                'update' => 'NO_ACTION'
            ])
            ->update();
    }
}
