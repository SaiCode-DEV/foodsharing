<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddClientKeyToFsMsg extends AbstractMigration
{
    public function change(): void
    {
        // Client-generated idempotency key for chat messages (12 random hex chars,
        // the size of the last uuid block). A retry of a failed send carries the
        // same key, so the unique index turns the second insert into a no-op
        // instead of a duplicate row. Unique per sender so a colliding key from
        // another sender can never swallow their message. Nullable: server-initiated
        // sends pass no key, and the daily maintenance nulls keys older than the
        // retry window to keep the table small - MariaDB allows multiple NULLs
        // in a unique index.
        $this->table('fs_msg')
            ->addColumn('client_key', 'char', [
                'limit' => 12,
                'null' => true,
                'default' => null,
                'after' => 'is_htmlentity_encoded',
                'comment' => 'client-generated idempotency key (12 hex chars), unique per sender and conversation, nulled by the daily cleanup after the retry window',
            ])
            // client_key leads, so the daily cleanup (client_key IS NOT NULL AND time < ?)
            // ranges over the few keys still set instead of scanning the table. The column
            // order does not change what the index enforces.
            ->addIndex(['client_key', 'conversation_id', 'foodsaver_id'], [
                'unique' => true,
                'name' => 'unique_conversation_sender_client_key',
            ])
            ->update();
    }
}
