<?php

declare(strict_types=1);

use Phinx\Db\Adapter\MysqlAdapter;
use Phinx\Migration\AbstractMigration;

/**
 * Adds the table fs_region_statistics which contains additional statistics for regions (except for working groups). The
 * table is filled in the nightly maintenance.
 */
final class AddRegionStatisticsTable extends AbstractMigration
{
    public function change(): void
    {
        $this->table('fs_region_statistics', [
            'id' => false,
            'primary_key' => ['region_id'],
            'engine' => 'InnoDB',
            'encoding' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'comment' => 'Contains statistics for each region which are precomputed regularly',
        ])
            ->addColumn('region_id', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
                'comment' => 'Id of the region, referring to table fs_bezirk.'
            ])
            ->addColumn('last_modified', 'datetime', [
                'null' => true,
                'default' => null,
                'after' => 'region_id',
                'comment' => 'The last time that the statistics for the region were updated'
            ])
            ->addColumn('active_home_region_foodsavers', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => MysqlAdapter::INT_REGULAR,
                'signed' => false,
                'after' => 'region_id',
                'comment' => 'Number of verified foodsavers with home region within the region that logged in within the last month'
            ])
            ->addColumn('active_coorporations', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => MysqlAdapter::INT_REGULAR,
                'signed' => false,
                'after' => 'active_home_region_foodsavers',
                'comment' => 'Number of currently cooperating stores'
            ])
            ->addColumn('pickups_last_month', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => MysqlAdapter::INT_REGULAR,
                'signed' => false,
                'after' => 'active_coorporations',
                'comment' => 'Number of filled pickup slots in the last month'
            ])
            ->addColumn('saved_food_weight_last_month', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => MysqlAdapter::INT_REGULAR,
                'signed' => false,
                'after' => 'pickups_last_month',
                'comment' => 'Weight of saved food of the last month. Rounded to full kg.'
            ])
            ->addColumn('active_food_share_points', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => MysqlAdapter::INT_MEDIUM,
                'signed' => false,
                'after' => 'saved_food_weight_last_month',
                'comment' => 'Number of active food share points'
            ])
            ->addColumn('food_baskets_last_month', 'integer', [
                'null' => false,
                'default' => '0',
                'limit' => MysqlAdapter::INT_MEDIUM,
                'signed' => false,
                'after' => 'active_food_share_points',
                'comment' => 'Number of foodbaskets in the last month'
            ])
            ->addForeignKey('region_id', 'fs_bezirk', 'id', [
                'delete' => 'CASCADE',
                'update' => 'CASCADE',
            ])
            ->create();
    }
}
