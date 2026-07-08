<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Report;

use Codeception\Test\Unit;
use Foodsharing\Modules\Report\DTO\UpdateReportData;
use Foodsharing\Modules\Report\ReportGateway;
use Tests\Support\UnitTester;

class ReportGatewayTest extends Unit
{
    /**
     * @var UnitTester
     */
    protected $tester;

    /**
     * @var ReportGateway|null
     */
    private $gateway;

    private $foodsaver;

    public function _before()
    {
        $this->gateway = $this->tester->get(ReportGateway::class);
        $this->foodsaver = $this->tester->createFoodsaver();
    }

    public function testUpdateReport(): void
    {
        $region = $this->tester->createRegion();

        $this->tester->addForumThread($region['id'], $this->foodsaver['id'], false, [
            'id' => 123,
            'name' => 'Thread 123',
        ]);

        $this->tester->addForumThread($region['id'], $this->foodsaver['id'], false, [
            'id' => 456,
            'name' => 'Thread 456',
        ]);

        $reportId = $this->tester->haveInDatabase('fs_report', [
            'foodsaver_id' => $this->foodsaver['id'],
            'status' => 'old_status',
            'consequence' => 'old_consequence',
            'forum_thread_id' => 123,
            'reminder_at' => null,
            'reminder_sent' => 1,
        ]);

        $updateData = new UpdateReportData();
        $updateData->status = 'new_status';
        $updateData->consequence = 'new_consequence';
        $updateData->forumThreadId = 456;
        $updateData->reminderAt = '2030-01-01 10:00:00';

        $this->gateway->updateReport($reportId, $updateData);

        $this->tester->seeInDatabase('fs_report', [
            'id' => $reportId,
            'status' => 'new_status',
            'consequence' => 'new_consequence',
            'forum_thread_id' => 456,
            'reminder_at' => '2030-01-01 10:00:00',
            'reminder_sent' => 0, // Should be reset
        ]);
    }

    public function testGetReportsWithDueReminders(): void
    {
        // 1. Due reminder (past), not sent
        $report1Id = $this->tester->haveInDatabase('fs_report', [
            'foodsaver_id' => $this->foodsaver['id'],
            'reminder_at' => date('Y-m-d H:i:s', time() - 3600),
            'reminder_sent' => 0,
        ]);

        // 2. Future reminder, not sent
        $this->tester->haveInDatabase('fs_report', [
            'foodsaver_id' => $this->foodsaver['id'],
            'reminder_at' => date('Y-m-d H:i:s', time() + 3600),
            'reminder_sent' => 0,
        ]);

        // 3. Due reminder (past), but already sent
        $this->tester->haveInDatabase('fs_report', [
            'foodsaver_id' => $this->foodsaver['id'],
            'reminder_at' => date('Y-m-d H:i:s', time() - 3600),
            'reminder_sent' => 1,
        ]);

        // 4. Null reminder, not sent
        $this->tester->haveInDatabase('fs_report', [
            'foodsaver_id' => $this->foodsaver['id'],
            'reminder_at' => null,
            'reminder_sent' => 0,
        ]);

        $reports = $this->gateway->getReportsWithDueReminders();

        $this->assertCount(1, $reports);
        $this->assertEquals($report1Id, $reports[0]->id);
    }
}
