<?php

declare(strict_types=1);

use Foodsharing\Modules\Core\DBConstants\Voting\VotingNotificationType;
use Phinx\Db\Adapter\MysqlAdapter;
use Phinx\Migration\AbstractMigration;

final class AddPollNotificationTracking extends AbstractMigration
{
    /**
     * Add notification tracking field to the poll table
     * to prevent duplicate notifications.
     *
     * Values:
     * 0 = No notifications sent
     * 1 = Start notification sent
     * 2 = Both start and end notifications sent
     */
    public function change(): void
    {
        $this->table('fs_poll')
            ->addColumn('notifications_sent', 'integer', [
                'null' => false,
                'default' => VotingNotificationType::BOTH_SENT, // Default to BOTH_SENT for existing polls to avoid retroactive notifications
                'after' => 'shuffle_options',
                'limit' => MysqlAdapter::INT_TINY,
            ])
            ->addIndex(['notifications_sent', 'start'], ['name' => 'idx_poll_start_notification'])
            ->addIndex(['notifications_sent', 'end'], ['name' => 'idx_poll_end_notification'])
            ->update();
    }
}
