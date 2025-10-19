<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

class AddMailboxIndices extends AbstractMigration
{
    public function change(): void
    {
        // Speed up mailbox member lookups
        // The combination (foodsaver_id, mailbox_id) is not covered by the
        // primary key (mailbox_id, foodsaver_id) but needed by the unread
        // message count query that runs very often. This allows the subquery
        // SELECT mailbox_id FROM fs_mailbox_member WHERE foodsaver_id = ? to be
        // satisfied directly from the index without touching the table rows.
        $this->table('fs_mailbox_member')
            ->addIndex(['foodsaver_id', 'mailbox_id'], ['name' => 'idx_fs_mailbox_member_foodsaver_mailbox'])
            ->update();
    }
}
