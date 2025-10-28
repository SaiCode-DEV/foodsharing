<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

class AddIdxTargetFunctionRegionToFsRegionFunction extends AbstractMigration
{
    public function change(): void
    {
        // Add a composite covering index to speed up lookups that filter by
        // target_id and function_id and obtain region_id as result:
        //
        //     SELECT `region_id` FROM `fs_region_function`
        //          WHERE `target_id` = ? AND `function_id` = ?
        //
        // The database can use the index to satisfy the query without touching
        // the table rows themselves using this index.

        $table = $this->table('fs_region_function');
        $table->addIndex(['target_id', 'function_id', 'region_id'], ['name' => 'idxx_fs_region_function_target_function_region'])
            ->update();
    }
}
