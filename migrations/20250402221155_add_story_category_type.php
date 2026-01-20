<?php

declare(strict_types=1);

use Foodsharing\Modules\Categories\StoreCategoryType;
use Phinx\Db\Adapter\MysqlAdapter;
use Phinx\Migration\AbstractMigration;

final class AddStoryCategoryType extends AbstractMigration
{
    public function change(): void
    {
        $this->table('fs_betrieb_kategorie')
            ->addColumn('type', 'integer', [
                'null' => false,
                'default' => StoreCategoryType::PICKUP->value,
                'limit' => MysqlAdapter::INT_TINY,
                'signed' => false,
                'comment' => 'Type of the category: 0 = pickup, 1 = giving, 2 = orga',
            ])
            ->update();
    }
}
