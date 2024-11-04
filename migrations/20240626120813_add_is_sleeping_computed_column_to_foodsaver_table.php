<?php

declare(strict_types=1);

use Foodsharing\Modules\Core\DBConstants\Foodsaver\SleepStatus;
use Phinx\Db\Adapter\MysqlAdapter;
use Phinx\Migration\AbstractMigration;

final class AddIsSleepingComputedColumnToFoodsaverTable extends AbstractMigration
{
    public function change(): void
    {
        $temporary = SleepStatus::TEMP;
        $full = SleepStatus::FULL;
        $this->execute("ALTER TABLE fs_foodsaver
            ADD is_sleeping TINYINT(1) GENERATED ALWAYS AS (
                IF(sleep_status = {$temporary},
                    sleep_from < NOW() AND NOW() < sleep_until,
                    sleep_status = {$full}
                )
            ) VIRTUAL
            COMMENT \"calculated column. Indicates, whether the user is currently sleeping\"
            AFTER sleep_until");

        $this->table('fs_foodsaver_archive')->addColumn('is_sleeping', 'integer', [
            'null' => true,
            'default' => null,
            'limit' => MysqlAdapter::INT_TINY,
        ])->update();
    }
}
