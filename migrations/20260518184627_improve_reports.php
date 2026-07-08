<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class ImproveReports extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('fs_report');

        // add forum thread link and status/consequence fields
        if (!$table->hasColumn('forum_thread_id')) {
            $table->addColumn('forum_thread_id', 'integer', [
                'null' => true,
                'default' => null,
                'signed' => false,
                'after' => 'betrieb_id',
            ]);
        }

        if (!$table->hasColumn('status')) {
            $table->addColumn('status', 'string', [
                'null' => true,
                'default' => 'reports.statuses.to_do',
                'limit' => 64,
                'after' => 'forum_thread_id',
            ]);
        }

        if (!$table->hasColumn('consequence')) {
            $table->addColumn('consequence', 'string', [
                'null' => true,
                'default' => null,
                'limit' => 64,
                'after' => 'status',
            ]);
        }

        if (!$table->hasColumn('reminder_at')) {
            $table->addColumn('reminder_at', 'datetime', [
                'null' => true,
                'default' => null,
                'after' => 'consequence',
            ]);
        }

        if (!$table->hasColumn('reminder_sent')) {
            $table->addColumn('reminder_sent', 'boolean', [
                'null' => false,
                'default' => false,
                'after' => 'reminder_at',
            ]);
        }

        // remove legacy committed column if present
        if ($table->hasColumn('committed')) {
            $table->removeColumn('committed');
        }

        // add foreign key to forum thread (fs_theme) - set to NULL if thread is deleted
        $table->addForeignKey('forum_thread_id', 'fs_theme', ['id'], [
            'delete' => 'SET_NULL',
            'update' => 'NO_ACTION',
        ]);

        $table->update();
    }
}
