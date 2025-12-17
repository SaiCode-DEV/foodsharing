<?php

declare(strict_types=1);

use Foodsharing\Modules\Core\DBConstants\Achievement\DuplicateMode;
use Foodsharing\Modules\Core\DBConstants\Achievement\VisibilityType;
use Phinx\Db\Adapter\MysqlAdapter;
use Phinx\Migration\AbstractMigration;

final class AchievementTableUpdates extends AbstractMigration
{
    public function change(): void
    {
        $this->table('fs_achievement')
            ->removeColumn('is_requestable_by_foodsaver')
            ->addColumn('visibility_type', 'integer', [
                'default' => VisibilityType::STORE_MANAGERS->value,
                'null' => false,
                'limit' => MysqlAdapter::INT_TINY,
                'signed' => false,
            ])
            ->addColumn('duplicate_mode', 'integer', [
                'default' => DuplicateMode::OVERRIDE->value,
                'null' => false,
                'limit' => MysqlAdapter::INT_TINY,
                'signed' => false,
            ])
            ->update();
    }
}
