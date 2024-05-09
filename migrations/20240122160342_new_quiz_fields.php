<?php

declare(strict_types=1);

use Phinx\Db\Adapter\MysqlAdapter;
use Phinx\Migration\AbstractMigration;

final class NewQuizFields extends AbstractMigration
{
    public function change(): void
    {
        $this->table('fs_quiz')
            ->addColumn('questcount_untimed', 'integer', [
                'null' => true,
                'limit' => MysqlAdapter::INT_SMALL,
                'signed' => false,
                'comment' => 'number of questions that need to be answered when not using a time limit. Can be null to disable untimed quizzes.',
            ])
            ->addColumn('is_desc_htmlentity_encoded', 'boolean', [
                'null' => false,
                'default' => '1',
                'limit' => MysqlAdapter::INT_TINY,
                'after' => 'desc',
                'comment' => 'Whether the quiz description is html encoded.',
            ])
            ->update();
    }
}
