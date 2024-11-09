<?php

declare(strict_types=1);

use Phinx\Db\Adapter\MysqlAdapter;
use Phinx\Migration\AbstractMigration;

final class AddStoreHygieneRequirementColumn extends AbstractMigration
{
    public function change(): void
    {
        $this->table('fs_betrieb')
            ->addColumn('hygiene_requirement', 'integer', [
                'limit' => MysqlAdapter::INT_TINY,
                'signed' => false,
                'null' => false,
            ])
            ->update();
    }
}
