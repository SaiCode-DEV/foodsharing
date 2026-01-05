<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class RemoveFoodsaverOptionsColumn extends AbstractMigration
{
    /**
     * Removes the 'option' column from fs_foodsaver. It was replaced by the fs_foodsaver_has_options table in MR 3146.
     */
    public function change(): void
    {
        $this->table('fs_foodsaver')->removeColumn('option')->update();
    }
}
