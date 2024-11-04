<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class RemoveUnusedFsIdFromBasketTable extends AbstractMigration
{
    public function change(): void
    {
        $this->table('fs_basket')->removeColumn('fs_id')->update();
    }
}
