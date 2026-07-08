<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Report;

use Codeception\Test\Unit;
use Foodsharing\Modules\Bell\BellGateway;
use Foodsharing\Modules\Bell\DTO\Bell;
use Foodsharing\Modules\Core\DBConstants\Region\WorkgroupFunction;
use Foodsharing\Modules\Group\GroupFunctionGateway;
use Foodsharing\Modules\Report\DTO\ReportReminderInfo;
use Foodsharing\Modules\Report\ReportGateway;
use Foodsharing\Modules\Report\ReportNotificationService;

class ReportNotificationServiceTest extends Unit
{
    private $reportGateway;
    private $bellGateway;
    private $groupFunctionGateway;
    private ReportNotificationService $service;

    public function _before(): void
    {
        $this->reportGateway = $this->createMock(ReportGateway::class);
        $this->bellGateway = $this->createMock(BellGateway::class);
        $this->groupFunctionGateway = $this->createMock(GroupFunctionGateway::class);

        $this->service = new ReportNotificationService(
            $this->reportGateway,
            $this->bellGateway,
            $this->groupFunctionGateway
        );
    }

    public function testNotifyForRemindersSendsNotificationsAndMarksAsSent(): void
    {
        // Mock a report with a due reminder
        $reportInfo = ReportReminderInfo::createFromArray([
            'id' => 123,
            'foodsaver_id' => 456,
            'regionId' => 789,
        ]);

        $this->reportGateway
            ->expects($this->once())
            ->method('getReportsWithDueReminders')
            ->willReturn([$reportInfo]);

        // Mock getting admins for the report
        $notifyUsers = [1, 2];
        $this->groupFunctionGateway
            ->expects($this->once())
            ->method('getFunctionGroupAdminsForRegion')
            ->with(789, WorkgroupFunction::REPORT)
            ->willReturn($notifyUsers);

        // Expect the bell notification to be sent
        $this->bellGateway
            ->expects($this->once())
            ->method('addBellForUsers')
            ->with($this->equalTo($notifyUsers), $this->callback(function (Bell $bell) use ($reportInfo) {
                return $bell->vars['reportId'] === $reportInfo->id
                    && str_starts_with($bell->identifier, 'report-reminder')
                    && str_contains($bell->link_attributes['href'], 'id=123');
            }));

        // Expect the reminder to be marked as sent
        $this->reportGateway
            ->expects($this->once())
            ->method('markReminderSent')
            ->with(123);

        $this->service->notifyForReminders();
    }

    public function testNotifyForRemindersHandlesNoAdminsGracefully(): void
    {
        $reportInfo = ReportReminderInfo::createFromArray([
            'id' => 124,
            'foodsaver_id' => 457,
            'regionId' => 790,
        ]);

        $this->reportGateway
            ->expects($this->once())
            ->method('getReportsWithDueReminders')
            ->willReturn([$reportInfo]);

        // No admins found
        $this->groupFunctionGateway
            ->expects($this->once())
            ->method('getFunctionGroupAdminsForRegion')
            ->with(790, WorkgroupFunction::REPORT)
            ->willReturn([]);

        // Should NOT send notifications
        $this->bellGateway
            ->expects($this->never())
            ->method('addBellForUsers');

        // Should STILL mark reminder as sent
        $this->reportGateway
            ->expects($this->once())
            ->method('markReminderSent')
            ->with(124);

        $this->service->notifyForReminders();
    }
}
