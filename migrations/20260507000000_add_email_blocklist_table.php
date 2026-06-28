<?php

declare(strict_types=1);

use Phinx\Db\Adapter\MysqlAdapter;
use Phinx\Migration\AbstractMigration;

/**
 * Extend existing fs_email_blacklist table for managing blocked email patterns during registration.
 */
final class AddEmailBlocklistTable extends AbstractMigration
{
    public function change(): void
    {
        // Use raw SQL to add id as PRIMARY KEY AUTO_INCREMENT (Phinx Table API doesn't handle this well for existing tables)
        $this->execute('ALTER TABLE fs_email_blacklist ADD COLUMN id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY FIRST');

        // Use Table API for remaining modifications
        $this->table('fs_email_blacklist')
            ->changeColumn('email', 'string', [
                'limit' => 255,
                'null' => false,
                'comment' => 'Email pattern to block (can include wildcards like *@example.com or user@*)',
            ])
            ->addIndex(['email'], ['unique' => true, 'name' => 'idx_email'])
            ->renameColumn('since', 'created_at')
            ->changeColumn('created_at', 'timestamp', [
                'null' => false,
                'default' => 'CURRENT_TIMESTAMP',
            ])
            ->changeColumn('reason', 'text', [
                'null' => true,
                'comment' => 'Reason or description for blocking this pattern',
                'limit' => MysqlAdapter::TEXT_MEDIUM,
            ])
            ->addColumn('active', 'boolean', [
                'default' => true,
                'null' => false,
                'comment' => 'Whether this blocklist entry is active',
            ])
            ->addColumn('created_by', 'integer', [
                'signed' => false,
                'null' => true,
                'comment' => 'ID of the admin who created this entry',
            ])
            ->addColumn('updated_at', 'datetime', [
                'null' => true,
                'comment' => 'Last update timestamp',
            ])
            ->addColumn('updated_by', 'integer', [
                'signed' => false,
                'null' => true,
                'comment' => 'ID of the admin who last updated this entry',
            ])
            ->addIndex(['active'])
            ->addForeignKey('created_by', 'fs_foodsaver', 'id', [
                'delete' => 'SET_NULL',
                'update' => 'CASCADE',
            ])
            ->addForeignKey('updated_by', 'fs_foodsaver', 'id', [
                'delete' => 'SET_NULL',
                'update' => 'CASCADE',
            ])
            ->save();
    }
}
