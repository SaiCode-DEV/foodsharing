<?php

declare(strict_types=1);

use Phinx\Db\Adapter\MysqlAdapter;
use Phinx\Migration\AbstractMigration;

final class StoreApplicationTextRequired extends AbstractMigration
{
    public function change(): void
    {
        // Change apply_text_requirement default to 1 (required)
        $this->table('fs_betrieb')
        ->changeColumn('apply_text_requirement', 'integer', [
            'limit' => MysqlAdapter::INT_TINY,
            'default' => 1,
            'signed' => false,
            'null' => false,
        ])
        ->update();

        // Update existing records to have apply_text_requirement = 1
        $this->execute('UPDATE fs_betrieb SET apply_text_requirement = 1;');
    }
}
