<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddWebauthnCredentialsTable extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('fs_webauthn_credentials', [
            'id' => false,
            'primary_key' => ['id'],
        ]);

        $table
            ->addColumn('id', 'string', [
                'limit' => 26, // ULID length
                'null' => false,
            ])
            ->addColumn('public_key_credential_id', 'text', [
                'null' => false,
            ])
            ->addColumn('type', 'string', [
                'limit' => 255,
                'null' => false,
            ])
            ->addColumn('transports', 'json', [
                'null' => false,
            ])
            ->addColumn('attestation_type', 'string', [
                'limit' => 255,
                'null' => false,
            ])
            ->addColumn('trust_path', 'text', [
                'null' => false,
            ])
            ->addColumn('aaguid', 'string', [
                'limit' => 36,
                'null' => false,
            ])
            ->addColumn('credential_public_key', 'text', [
                'null' => false,
            ])
            ->addColumn('user_handle', 'integer', [
                'limit' => 10,
                'signed' => false,
                'null' => false,
            ])
            ->addColumn('counter', 'integer', [
                'signed' => false,
                'null' => false,
                'default' => 0,
            ])
            ->addColumn('other_ui', 'json', [
                'null' => true,
            ])
            ->addColumn('name', 'string', [
                'limit' => 255,
                'null' => true,
            ])
            ->addColumn('rp_id', 'string', [
                'limit' => 255,
                'null' => false,
                'default' => '',
                'comment' => 'The Relying Party ID (domain) where this passkey was registered',
            ])
            ->addColumn('created_at', 'datetime', [
                'null' => false,
            ])
            ->addColumn('last_used_at', 'datetime', [
                'null' => true,
            ])
            ->addIndex(['public_key_credential_id'], [
                'unique' => true,
                'name' => 'idx_public_key_credential_id',
            ])
            ->addIndex(['user_handle'], [
                'name' => 'idx_user_handle',
            ])
            ->addIndex(['user_handle', 'rp_id'], [
                'name' => 'idx_user_handle_rp_id',
            ])
            ->addForeignKey('user_handle', 'fs_foodsaver', 'id', [
                'delete' => 'CASCADE',
                'update' => 'CASCADE',
            ])
            ->create();
    }
}
