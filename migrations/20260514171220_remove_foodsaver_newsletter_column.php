<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class RemoveFoodsaverNewsletterColumn extends AbstractMigration
{
    public function change(): void
    {
        $this->table('fs_foodsaver')
            ->removeColumn('newsletter')
            ->update();
    }
}
