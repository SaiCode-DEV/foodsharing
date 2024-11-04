<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class RemoveColumnsInFoodsaverTable extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('fs_foodsaver');
        $table->removeColumn('new_bezirk')
            ->removeColumn('want_new')
            ->removeColumn('type')
            ->removeColumn('beta')
            ->removeColumn('data')
            ->removeColumn('admin')
            ->removeColumn('contact_public')
            ->removeColumn('orgateam')
            ->update();
    }
}
