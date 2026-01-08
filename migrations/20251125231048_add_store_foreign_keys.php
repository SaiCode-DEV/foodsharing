<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddStoreForeignKeys extends AbstractMigration
{
    /**
     * Adds missing foreign keys to fs_betrieb and alters the corresponding columns.
     */
    public function change(): void
    {
        // Update columns
        $this->table('fs_betrieb')
            ->changeColumn('team_conversation_id', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
                'after' => 'prefetchtime',
            ])
            ->changeColumn('springer_conversation_id', 'integer', [
                'null' => false,
                'limit' => 10,
                'signed' => false,
                'after' => 'team_conversation_id',
            ])
            ->update();

        // Add foreign keys
        $this->table('fs_betrieb')
            ->addForeignKey('kette_id', 'fs_chain', 'id', [
                'delete' => 'SET_NULL',
                'update' => 'NO_ACTION'
            ])
            ->addForeignKey('bezirk_id', 'fs_bezirk', 'id', [
                'delete' => 'RESTRICT',
                'update' => 'NO_ACTION'
            ])
            ->addForeignKey('team_conversation_id', 'fs_conversation', 'id', [
                'delete' => 'RESTRICT',
                'update' => 'NO_ACTION'
            ])
            ->addForeignKey('springer_conversation_id', 'fs_conversation', 'id', [
                'delete' => 'RESTRICT',
                'update' => 'NO_ACTION'
            ])
            ->update();
    }
}
