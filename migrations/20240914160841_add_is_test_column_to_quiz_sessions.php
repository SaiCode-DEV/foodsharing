<?php

declare(strict_types=1);

use Phinx\Db\Adapter\MysqlAdapter;
use Phinx\Migration\AbstractMigration;

final class AddIsTestColumnToQuizSessions extends AbstractMigration
{
    public function change(): void
    {
        $this->table('fs_quiz_session')->addColumn('is_test', 'integer', [
            'null' => false,
            'default' => '0',
            'limit' => MysqlAdapter::INT_TINY,
            'signed' => false,
            'comment' => 'Whether this quiz session is only for testing purposes',
        ])->update();
    }
}
