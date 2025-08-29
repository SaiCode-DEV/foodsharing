<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddResourceTables extends AbstractMigration
{
    public function change(): void
    {
        $this->table('fs_resource')
            ->addColumn('foodsaver_id', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
            ])
            ->addColumn('name', 'string', [
                'null' => false,
                'limit' => 35,
            ])
            ->addColumn('description', 'text', [
                'null' => true,
                'default' => null,
            ])
            ->addColumn('is_private', 'boolean', [
                'null' => true,
                'default' => 0,
            ])
            ->addColumn('openness', 'integer', [
                'limit' => 1,
                'signed' => false,
                'default' => 3,
            ])
            ->addColumn('images', 'text', [
                'null' => true,
                'default' => null,
            ])
            ->addIndex('foodsaver_id')
            ->addForeignKey('foodsaver_id', 'fs_foodsaver', 'id', ['delete' => 'CASCADE'])
            ->create();

        $this->table('fs_resource_category')
            ->addColumn('name', 'string', [
                'null' => false,
                'limit' => 35,
            ])
            ->create();

        $this->table('fs_resource_has_category', [
            'id' => false,
            'primary_key' => ['resource_id', 'category_id'],
        ])
            ->addColumn('resource_id', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
            ])
            ->addColumn('category_id', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
            ])
            ->addForeignKey('resource_id', 'fs_resource', 'id', ['delete' => 'CASCADE'])
            ->addForeignKey('category_id', 'fs_resource_category', 'id', ['delete' => 'CASCADE'])
            ->create();

        $this->table('fs_foodsaver_has_favorite_resource', [
            'id' => false,
            'primary_key' => ['foodsaver_id', 'resource_id'],
        ])
            ->addColumn('foodsaver_id', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
            ])
            ->addColumn('resource_id', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
            ])
            ->addIndex('foodsaver_id')
            ->addIndex('resource_id')
            ->addForeignKey('foodsaver_id', 'fs_foodsaver', 'id', ['delete' => 'CASCADE'])
            ->addForeignKey('resource_id', 'fs_resource', 'id', ['delete' => 'CASCADE'])
            ->create();
    }
}
