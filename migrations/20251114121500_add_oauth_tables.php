<?php

declare(strict_types=1);

use Phinx\Db\Adapter\MysqlAdapter;
use Phinx\Migration\AbstractMigration;

final class AddOauthTables extends AbstractMigration
{
    public function change(): void
    {
        $this->createClientsTable();
        $this->createAccessTokensTable();
        $this->createRefreshTokensTable();
        $this->createAuthCodesTable();
        $this->createUserConsentsTable();
    }

    private function createClientsTable(): void
    {
        if ($this->hasTable('oauth_clients')) {
            return;
        }

        $this->table('oauth_clients', [
            'id' => false,
            'primary_key' => ['identifier'],
            'engine' => 'InnoDB',
            'encoding' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
        ])
            ->addColumn('identifier', 'string', ['limit' => 100])
            ->addColumn('name', 'string', ['limit' => 190])
            ->addColumn('confidential', 'boolean', ['default' => true])
            ->addColumn('active', 'boolean', ['default' => true])
            ->addColumn('redirect_uris', 'text', ['limit' => MysqlAdapter::TEXT_MEDIUM, 'null' => false])
            ->addColumn('scopes', 'text', ['limit' => MysqlAdapter::TEXT_MEDIUM, 'null' => false])
            ->addColumn('grant_types', 'text', ['limit' => MysqlAdapter::TEXT_MEDIUM, 'null' => false])
            ->addColumn('secret_hash', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('required_region_ids', 'text', ['limit' => MysqlAdapter::TEXT_MEDIUM, 'null' => true])
            ->addColumn('changed_by', 'integer', ['signed' => false, 'default' => 0])
            ->addColumn('created_at', 'datetime', ['null' => false])
            ->addColumn('updated_at', 'datetime', ['null' => false])
            ->create();
    }

    private function createAccessTokensTable(): void
    {
        if ($this->hasTable('oauth_access_tokens')) {
            return;
        }

        $this->table('oauth_access_tokens', [
            'id' => false,
            'primary_key' => ['identifier'],
            'engine' => 'InnoDB',
            'encoding' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
        ])
            ->addColumn('identifier', 'string', ['limit' => 100])
            ->addColumn('client_identifier', 'string', ['limit' => 100])
            ->addColumn('user_identifier', 'string', ['limit' => 100, 'null' => true])
            ->addColumn('scopes', 'text', ['limit' => MysqlAdapter::TEXT_MEDIUM, 'null' => false])
            ->addColumn('expires_at', 'datetime', ['null' => false])
            ->addColumn('revoked', 'boolean', ['default' => false])
            ->addColumn('created_at', 'datetime', ['null' => false])
            ->addIndex(['client_identifier'], ['name' => 'idx_oauth_access_tokens_client'])
            ->addIndex(['user_identifier'], ['name' => 'idx_oauth_access_tokens_user'])
            ->addIndex(['expires_at'], ['name' => 'idx_oauth_access_tokens_expires'])
            ->addIndex(['revoked'], ['name' => 'idx_oauth_access_tokens_revoked'])
            ->addForeignKey('client_identifier', 'oauth_clients', 'identifier', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->create();
    }

    private function createRefreshTokensTable(): void
    {
        if ($this->hasTable('oauth_refresh_tokens')) {
            return;
        }

        $this->table('oauth_refresh_tokens', [
            'id' => false,
            'primary_key' => ['identifier'],
            'engine' => 'InnoDB',
            'encoding' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
        ])
            ->addColumn('identifier', 'string', ['limit' => 100])
            ->addColumn('access_token_identifier', 'string', ['limit' => 100])
            ->addColumn('expires_at', 'datetime', ['null' => false])
            ->addColumn('revoked', 'boolean', ['default' => false])
            ->addColumn('created_at', 'datetime', ['null' => false])
            ->addIndex(['access_token_identifier'], ['name' => 'idx_oauth_refresh_tokens_access_token'])
            ->addIndex(['revoked'], ['name' => 'idx_oauth_refresh_tokens_revoked'])
            ->addForeignKey('access_token_identifier', 'oauth_access_tokens', 'identifier', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->create();
    }

    private function createAuthCodesTable(): void
    {
        if ($this->hasTable('oauth_auth_codes')) {
            return;
        }

        $this->table('oauth_auth_codes', [
            'id' => false,
            'primary_key' => ['identifier'],
            'engine' => 'InnoDB',
            'encoding' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
        ])
            ->addColumn('identifier', 'string', ['limit' => 100])
            ->addColumn('client_identifier', 'string', ['limit' => 100])
            ->addColumn('user_identifier', 'string', ['limit' => 100, 'null' => true])
            ->addColumn('scopes', 'text', ['limit' => MysqlAdapter::TEXT_MEDIUM, 'null' => false])
            ->addColumn('redirect_uri', 'text', ['limit' => MysqlAdapter::TEXT_MEDIUM, 'null' => true])
            ->addColumn('nonce', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('expires_at', 'datetime', ['null' => false])
            ->addColumn('revoked', 'boolean', ['default' => false])
            ->addColumn('code_challenge', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('code_challenge_method', 'string', ['limit' => 20, 'null' => true])
            ->addColumn('created_at', 'datetime', ['null' => false])
            ->addIndex(['client_identifier'], ['name' => 'idx_oauth_auth_codes_client'])
            ->addIndex(['user_identifier'], ['name' => 'idx_oauth_auth_codes_user'])
            ->addIndex(['expires_at'], ['name' => 'idx_oauth_auth_codes_expires'])
            ->addForeignKey('client_identifier', 'oauth_clients', 'identifier', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->create();
    }

    private function createUserConsentsTable(): void
    {
        if ($this->hasTable('oauth_user_consents')) {
            return;
        }

        $this->table('oauth_user_consents', [
            'engine' => 'InnoDB',
            'encoding' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
        ])
            ->addColumn('user_id', 'integer', ['signed' => false])
            ->addColumn('client_identifier', 'string', ['limit' => 100])
            ->addColumn('scopes', 'text', ['limit' => MysqlAdapter::TEXT_MEDIUM, 'null' => false])
            ->addColumn('revoked_at', 'datetime', ['null' => true])
            ->addColumn('created_at', 'datetime', ['null' => false])
            ->addColumn('updated_at', 'datetime', ['null' => false])
            ->addIndex(['user_id', 'client_identifier'], ['name' => 'uniq_oauth_user_client', 'unique' => true])
            ->addForeignKey('user_id', 'fs_foodsaver', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->addForeignKey('client_identifier', 'oauth_clients', 'identifier', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->create();
    }
}
