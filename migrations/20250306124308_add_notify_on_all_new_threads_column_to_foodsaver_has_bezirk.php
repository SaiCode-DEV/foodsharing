<?php

declare(strict_types=1);

use Phinx\Db\Adapter\MysqlAdapter;
use Phinx\Migration\AbstractMigration;

final class AddNotifyOnAllNewThreadsColumnToFoodsaverHasBezirk extends AbstractMigration
{
    public function change(): void
    {
        $this->table('fs_foodsaver_has_bezirk')
            ->addColumn('notify_on_all_new_threads', 'integer', [
                'null' => true,
                'default' => null,
                'limit' => MysqlAdapter::INT_TINY,
                'signed' => false,
                'comment' => 'Whether to send a bell for every new thread in the forum. NULL for default value.',
            ])->update();
    }
}
