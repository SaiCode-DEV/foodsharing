<?php

namespace Foodsharing\Modules\Report;

use Foodsharing\Modules\Bell\BellGateway;
use Foodsharing\Modules\Bell\DTO\Bell;
use Foodsharing\Modules\Core\DBConstants\Bell\BellType;
use Foodsharing\Modules\Core\DBConstants\Region\WorkgroupFunction;
use Foodsharing\Modules\Group\GroupFunctionGateway;
use Foodsharing\Utility\ConsoleHelper;

class ReportNotificationService
{
    public function __construct(
        private readonly ReportGateway $reportGateway,
        private readonly BellGateway $bellGateway,
        private readonly GroupFunctionGateway $groupFunctionGateway
    ) {
    }

    /**
     * Sends reminder notifications for reports where reminder_at has been reached.
     *
     * Fetches all reports with reminder_at <= now, sends bell notifications
     * to the report's assignees, and clears the reminder date.
     */
    public function notifyForReminders(): void
    {
        // Fetch reports with due reminders
        $reportsWithReminders = $this->reportGateway->getReportsWithDueReminders();

        $notificationsSent = 0;
        foreach ($reportsWithReminders as $report) {
            // Get users who should be notified: use group function gateway to find the report workgroup admins
            $notifyUsers = $this->groupFunctionGateway->getFunctionGroupAdminsForRegion($report->regionId, WorkgroupFunction::REPORT);

            if (!empty($notifyUsers)) {
                $bellData = Bell::create(
                    'report_reminder_title',
                    'report_reminder',
                    'fas fa-exclamation-circle',
                    ['href' => '/region?bid=' . $report->regionId . '&sub=reports&id=' . $report->id],
                    ['reportId' => $report->id],
                    BellType::createIdentifier(BellType::REPORT_REMINDER, $report->id)
                );

                $this->bellGateway->addBellForUsers($notifyUsers, $bellData);
                ++$notificationsSent;
            }

            // Mark the reminder as sent
            $this->reportGateway->markReminderSent($report->id);
        }

        // Log the number of notifications sent
        ConsoleHelper::info('Sent reminder notifications for ' . $notificationsSent . ' reports');
    }
}
