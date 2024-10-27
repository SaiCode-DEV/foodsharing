<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class RemoveUnusedRegionMailCols extends AbstractMigration
{
    public function change(): void
    {
        $this->table('fs_bezirk')
            ->removeColumn('email_pass') // Save
            ->removeColumn('email')
            ->update();
    }
}
