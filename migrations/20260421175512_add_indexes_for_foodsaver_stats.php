<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddIndexesForFoodsaverStats extends AbstractMigration
{
    public function change(): void
    {
        $buddyTable = $this->table('fs_buddy');
        // Optimizes WHERE confirmed = 1 GROUP BY foodsaver_id for buddy stats during the update of the foodsaver stats in StatsGateway::updateFoodsaverStats().
        $buddyTable
            ->addIndex(
                ['confirmed', 'foodsaver_id'],
                ['name' => 'idx_fs_buddy_confirmed_foodsaver']
            )
            ->update();
    }
}
