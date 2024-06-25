<?php

declare(strict_types=1);

use Phinx\Db\Adapter\MysqlAdapter;
use Phinx\Migration\AbstractMigration;

final class AddChainTables extends AbstractMigration
{
    public function change(): void
    {
        $this->table('fs_kette')->drop()->save();

        $this->table('fs_chain', [
            'id' => false,
            'primary_key' => ['id']
        ])
            ->addColumn('id', 'integer', [
                'null' => false,
                'signed' => false,
                'limit' => 10,
                'identity' => true,
                'comment' => 'unique id of the chain'
            ])
            ->addColumn('name', 'string', [
                'null' => false,
                'default' => null,
                'limit' => 120,
                'collation' => 'utf8mb4_unicode_ci',
                'encoding' => 'utf8mb4',
            ])
            ->addColumn('headquarters_zip', 'string', [
                'null' => true,
                'limit' => 5,
                'collation' => 'utf8mb4_unicode_ci',
                'encoding' => 'utf8mb4',
            ])
            ->addColumn('headquarters_city', 'string', [
                'null' => true,
                'limit' => 50,
                'collation' => 'utf8mb4_unicode_ci',
                'encoding' => 'utf8mb4',
            ])
            ->addColumn('status', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
            ])
            ->addColumn('modification_date', 'date', [
                'null' => false,
            ])
            ->addColumn('allow_press', 'integer', [
                'null' => false,
                'default' => 0,
                'limit' => MysqlAdapter::INT_TINY,
            ])
            ->addColumn('forum_thread', 'integer', [
                'null' => true,
                'signed' => false,
                'limit' => 10,
                'comment' => 'id of the chains forum thread'
            ])
            ->addForeignKey('forum_thread', 'fs_theme_post', 'id', ['delete' => 'SET NULL'])
            ->addColumn('notes', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 200,
                'collation' => 'utf8mb4_unicode_ci',
                'encoding' => 'utf8mb4',
                'comment' => 'Only visibe in the chain table'
            ])
            ->addColumn('common_store_information', 'text', [
                'null' => true,
                'default' => null,
                'limit' => MysqlAdapter::TEXT_MEDIUM,
                'collation' => 'utf8mb4_unicode_ci',
                'encoding' => 'utf8mb4',
                'comment' => 'Details displayed on store pages'
            ])
            ->save();

        $this->table('fs_key_account_manager', [
            'id' => false,
            'primary_key' => ['foodsaver_id', 'chain_id']
        ])
            ->addColumn('foodsaver_id', 'integer', [
                'null' => true,
                'signed' => false,
                'limit' => 10,
            ])
            ->addColumn('chain_id', 'integer', [
                'null' => true,
                'signed' => false,
                'limit' => 10,
            ])
            ->addForeignKey('foodsaver_id', 'fs_foodsaver', 'id')
            ->addForeignKey('chain_id', 'fs_chain', 'id')
            ->save();
    }
}
