<?php

declare(strict_types=1);

use Phinx\Db\Adapter\MysqlAdapter;
use Phinx\Migration\AbstractMigration;

final class DevDatabaseAdjustment extends AbstractMigration
{
    /**
     * Adjusts all tables in the dev database to the same structure as the production database.
     */
    public function change(): void
    {
        $this->table('fs_achievement')
            ->changeColumn('icon', 'string', ['null' => true, 'limit' => 100, 'comment' => 'the icon to display this achievement with'])
            ->changeColumn('name', 'string', ['null' => false, 'limit' => 255])
            ->changeColumn('description', 'string', ['null' => false, 'limit' => 255])
            ->update();

        $this->table('fs_bell')
            ->addIndex(['time'], ['name' => 'idx_fs_bell_time', 'unique' => false])
            ->update();

        $this->table('fs_betrieb_team')
            ->removeColumn('id')
            ->changeColumn('stat_fetchcount', 'integer', ['null' => false, 'limit' => 10, 'signed' => false])
            ->removeIndexByName('foodsaver_id')
            ->changePrimaryKey(['foodsaver_id', 'betrieb_id'])
            ->update();

        $this->table('fs_bezirk')
            ->changeColumn('stat_last_update', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
            ->changeColumn('stat_fetchweight', 'decimal', ['null' => false, 'default' => 0, 'precision' => 12, 'signed' => false, 'scale' => 2])
            ->update();

        $this->table('fs_feature_toggles')
            ->changeColumn('is_active', 'boolean', ['limit' => 1, 'default' => 0, 'null' => false])
            ->update();

        $this->table('fs_foodsaver')
            ->changeColumn('token', 'string', ['null' => false, 'limit' => 100])
            ->addIndex(['deleted_at'], ['name' => 'idx_fs_foodsaver_deleted_at', 'unique' => false])
            ->update();

        $this->table('fs_foodsaver_archive')
            ->changeColumn('geschlecht', 'integer', ['null' => true, 'default' => null, 'limit' => MysqlAdapter::INT_TINY, 'signed' => false])
            ->changeColumn('token', 'string', ['null' => false, 'limit' => 100])
            ->addIndex(['bezirk_id'], ['name' => 'foodsaver_FKIndex2', 'unique' => false])
            ->addIndex(['plz'], ['name' => 'plz', 'unique' => false])
            ->addIndex(['mailbox_id'], ['name' => 'mailbox_id', 'unique' => false])
            ->addIndex(['newsletter'], ['name' => 'newsletter', 'unique' => false])
            ->update();

        $this->table('fs_foodsaver_has_conversation')
            ->changeColumn('unread', 'smallinteger', ['null' => false, 'default' => 1, 'signed' => true])
            ->update();

        $this->table('fs_fsreports_has_wallpost')
            ->dropForeignKey('fsreports_id')
            ->addForeignKey('fsreports_id', 'fs_foodsaver', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->update();

        $this->table('fs_region_function')
            ->changeColumn('region_id', 'integer', ['signed' => false, 'null' => false])
            ->removeIndexByName('idxx_fs_region_function_target_function_region')
            ->addIndex(['target_id', 'function_id', 'region_id'], ['name' => 'idx_fs_region_function_target_function_region', 'unique' => false])
            ->removeIndexByName('region_id')
            ->addIndex('region_id', ['name' => 'fs_region_function_ibfk_1', 'unique' => false])
            ->addIndex(['target_id', 'function_id'], ['name' => 'ux_fs_region_function_target_function', 'unique' => true])
            ->update();

        $this->table('fs_report_has_wallpost')->drop()->update();

        $this->table('fs_store_log')
            ->changeColumn('store_id', 'integer', ['null' => false, 'signed' => false, 'limit' => 10, 'comment' => 'ID of Store'])
            ->changeColumn('action', 'integer', ['null' => false, 'signed' => false, 'limit' => MysqlAdapter::INT_TINY, 'comment' => 'action type that was performed'])
            ->changeColumn('fs_id_a', 'integer', ['null' => false, 'signed' => false, 'limit' => 10, 'comment' => 'foodsaver_id who is doing the action'])
            ->changeColumn('fs_id_p', 'integer', ['null' => true, 'signed' => false, 'limit' => 10, 'comment' => 'to which foodsaver_id is it done to'])
            ->changeColumn('content', 'string', ['null' => true, 'limit' => MysqlAdapter::TEXT_REGULAR, 'comment' => 'Text from the store-wall-entry'])
            ->addIndex(['fs_id_a', 'action', 'date_reference'], ['name' => 'fsid_ref', 'unique' => false])
            ->update();

        $this->table('fs_theme_post')
            ->removeColumn('reply_post')
            ->update();

        $this->table('fs_wallpost')
            ->addIndex(['time'], ['name' => 'idx_wall_time', 'unique' => false])
            ->update();

        $this->table('uploads')
            ->changeColumn('user_id', 'integer', ['null' => true, 'signed' => false, 'limit' => 10])
            ->removeIndexByName('uuid')
            ->update();
    }
}
