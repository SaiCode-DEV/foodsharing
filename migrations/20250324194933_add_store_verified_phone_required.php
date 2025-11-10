<?php

declare(strict_types=1);

use Phinx\Db\Adapter\MysqlAdapter;
use Phinx\Migration\AbstractMigration;

final class AddStoreVerifiedPhoneRequired extends AbstractMigration
{
    public function change(): void
    {
        $this->table('fs_betrieb')
            ->addColumn('verified_requirement', 'integer', [
                'limit' => MysqlAdapter::INT_TINY,
                'default' => 1,
                'signed' => false,
                'null' => false,
            ])
            ->addColumn('phone_requirement', 'integer', [
                'limit' => MysqlAdapter::INT_TINY,
                'default' => 0,
                'signed' => false,
                'null' => false,
            ])
            ->addColumn('apply_text_requirement', 'integer', [
                'limit' => MysqlAdapter::INT_TINY,
                'default' => 0,
                'signed' => false,
                'null' => false,
            ])
            ->update();
    }
}
