<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Region;

use Codeception\Test\Unit;
use Foodsharing\Modules\Region\ForumGateway;
use Foodsharing\Modules\Report\DTO\ReportForListView;
use Tests\Support\UnitTester;

class ForumGatewayTest extends Unit
{
    /**
     * @var UnitTester
     */
    protected $tester;

    /**
     * @var ForumGateway|null
     */
    private $gateway;

    private $foodsaver;

    public function _before()
    {
        $this->gateway = $this->tester->get(ForumGateway::class);
        $this->foodsaver = $this->tester->createFoodsaver();
    }

    public function testGetReportLinkedToThread(): void
    {
        $region = $this->tester->createRegion();
        $thread = $this->tester->addForumThread($region['id'], $this->foodsaver['id'], false, [
            'name' => 'Report thread',
        ]);
        $threadId = $thread['id'];

        // Add a report linked to this thread
        $reportId = $this->tester->haveInDatabase('fs_report', [
            'foodsaver_id' => $this->foodsaver['id'],
            'reporter_id' => $this->foodsaver['id'],
            'msg' => 'A report message',
            'forum_thread_id' => $threadId,
            'time' => date('Y-m-d H:i:s'),
            'status' => 'Offen',
            'consequence' => null,
            'reminder_at' => null,
        ]);

        // Test fetching the report via thread ID
        $report = $this->gateway->getReportLinkedToThread($threadId);

        $this->assertInstanceOf(ReportForListView::class, $report);
        $this->assertEquals($reportId, $report->id);
        $this->assertEquals('A report message', $report->message);
        $this->assertEquals('Offen', $report->status);
        $this->assertEquals($this->foodsaver['id'], $report->reported->id);
        $this->assertEquals($this->foodsaver['id'], $report->reporter->id);
    }

    public function testGetReportLinkedToThreadReturnsNullWhenNoReport(): void
    {
        $report = $this->gateway->getReportLinkedToThread(99999);
        $this->assertNull($report);
    }
}
