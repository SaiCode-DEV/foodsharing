<?php

declare(strict_types=1);

use Phinx\Db\Adapter\MysqlAdapter;
use Phinx\Migration\AbstractMigration;

final class AddEventPublicColumn extends AbstractMigration
{
    public function change(): void
    {
        $this->table('fs_event')
        ->addColumn('is_public', 'integer', [
            'limit' => MysqlAdapter::INT_TINY,
            'signed' => false,
            'null' => false,
        ])
        ->update();
        // ALTER TABLE `fs_event` ADD `is_public` TINYINT(4) unsigned NOT NULL;
    }
}
