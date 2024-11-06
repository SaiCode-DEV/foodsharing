<?php

declare(strict_types=1);

use Phinx\Db\Adapter\MysqlAdapter;
use Phinx\Migration\AbstractMigration;

final class AddIsMandatoryColumnToQuestionTable extends AbstractMigration
{
    public function change(): void
    {
        $this->table('fs_question')
            ->addColumn('is_mandatory', 'integer', [
                'limit' => MysqlAdapter::INT_TINY,
                'signed' => false,
                'null' => false,
            ])
            ->update();
    }
}
