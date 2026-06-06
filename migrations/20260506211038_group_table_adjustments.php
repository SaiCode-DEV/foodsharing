<?php

declare(strict_types=1);

use Phinx\Db\Adapter\MysqlAdapter;
use Phinx\Migration\AbstractMigration;

final class GroupTableAdjustments extends AbstractMigration
{
    public function change(): void
    {
        $this->table('fs_group_category')
            ->addColumn('name', 'string', [
                'null' => false,
                'limit' => 35,
            ])
            ->create();

        $this->table('fs_bezirk')
            ->addColumn('application_prompt', 'string', [
                'null' => true,
                'default' => null,
                'limit' => MysqlAdapter::TEXT_REGULAR,
            ])
            ->addColumn('category_id', 'integer', [
                'null' => true,
                'limit' => 10,
                'signed' => false,
            ])
            ->addForeignKey('category_id', 'fs_group_category', 'id', ['delete' => 'SET_NULL'])
            ->removeColumn('banana_count')
            ->removeColumn('week_num')
            ->removeColumn('fetch_count')
            ->removeColumn('report_num')
            ->update();
    }
}
