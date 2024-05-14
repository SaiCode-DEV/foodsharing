<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class RemoveHomepageColumnInFoodsaverTable extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('fs_foodsaver');
        $table->removeColumn('homepage')
            ->update();
    }
}
