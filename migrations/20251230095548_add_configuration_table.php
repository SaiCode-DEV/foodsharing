<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Adds the 'configuration' table for storing individual key-value pairs.
 */
final class AddConfigurationTable extends AbstractMigration
{
    public function change(): void
    {
        $this->table('configuration', ['id' => false, 'primary_key' => ['key']])
            ->addColumn('key', 'string', ['null' => false, 'limit' => 50])
            ->addColumn('value', 'string', ['null' => false, 'limit' => 1000])
            ->addColumn('category', 'integer', ['null' => true, 'signed' => false, 'comment' => 'Optional category of the key-value pair'])
            ->create();
    }
}
