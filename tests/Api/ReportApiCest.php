<?php

declare(strict_types=1);

namespace Tests\Api;

use Codeception\Util\HttpCode;
use Foodsharing\Modules\Core\DBConstants\Region\WorkgroupFunction;
use Tests\Support\ApiTester;

/**
 * @group api-group-2
 */
class ReportApiCest
{
    private $parentRegion;
    private $region;
    private $reportGroup;
    private $reportGroupAdmin;
    private $arbitrationGroup;
    private $arbitrationGroupAdmin;
    private $subRegion;
    private $foodsaver;
    private $subRegionFoodsaver;
    private $foodsharer;

    public function _before(ApiTester $I): void
    {
        //Create regions
        $this->parentRegion = $I->createRegion();
        $this->region = $I->createRegion(null, ['parent_id' => $this->parentRegion['id']], false);
        $this->subRegion = $I->createRegion(null, ['parent_id' => $this->region['id']], false);

        // Create Workgroup and Workgroup Function for report
        $this->reportGroup = $I->createWorkingGroup('Meldungsbearbeitung', ['parent_id' => $this->region['id']]);
        $I->haveInDatabase('fs_region_function', ['region_id' => $this->reportGroup['id'], 'function_id' => WorkgroupFunction::REPORT, 'target_id' => $this->region['id']]);

        //create report admins and assign to report workgroup
        $this->reportGroupAdmin = $I->createStoreCoordinator(null, ['bezirk_id' => $this->region['id']]);
        $I->addRegionMember($this->reportGroup['id'], $this->reportGroupAdmin['id']);
        $I->addRegionAdmin($this->reportGroup['id'], $this->reportGroupAdmin['id']);

        // same for arbitration workgroup
        $this->arbitrationGroup = $I->createWorkingGroup('Schiedsstelle', ['parent_id' => $this->region['id']]);
        $I->haveInDatabase('fs_region_function', ['region_id' => $this->arbitrationGroup['id'], 'function_id' => WorkgroupFunction::ARBITRATION, 'target_id' => $this->region['id']]);
        $this->arbitrationGroupAdmin = $I->createStoreCoordinator(null, ['bezirk_id' => $this->region['id']]);
        $I->addRegionMember($this->arbitrationGroup['id'], $this->arbitrationGroupAdmin['id']);
        $I->addRegionAdmin($this->arbitrationGroup['id'], $this->arbitrationGroupAdmin['id']);

        $this->foodsaver = $I->createFoodsaver(null, ['bezirk_id' => $this->region['id']]);
        $this->subRegionFoodsaver = $I->createFoodsaver(null, ['bezirk_id' => $this->subRegion['id']]);
        $this->foodsharer = $I->createFoodsharer();
    }

    public function seeReportAboutFoodsaverInRegion(ApiTester $I): void
    {
        $I->login($this->reportGroupAdmin['email']);
        $I->addReport($this->foodsaver['id'], $this->foodsaver['id']);
        $I->sendGET($I->apiReportListForRegion($this->region['id']));
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseContainsJson(['reported' => ['id' => $this->foodsaver['id']], 'reporter' => ['id' => $this->foodsaver['id']]]);
    }

    public function cantSeeReportAboutFoodsaverInSubRegion(ApiTester $I): void
    {
        $I->login($this->reportGroupAdmin['email']);
        $I->addReport($this->foodsharer['id'], $this->subRegionFoodsaver['id']);
        $I->sendGET($I->apiReportListForRegion($this->region['id']));
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->cantSeeResponseContainsJson(['reported' => ['id' => $this->subRegionFoodsaver['id']], 'reporter' => ['id' => $this->foodsharer['id']]]);
    }

    public function dontSeeReportAboutSelf(ApiTester $I): void
    {
        $I->login($this->reportGroupAdmin['email']);
        $I->addReport($this->foodsharer['id'], $this->reportGroupAdmin['id']);
        $I->addReport($this->subRegionFoodsaver['id'], $this->reportGroupAdmin['id']);
        $I->sendGET($I->apiReportListForRegion($this->region['id']));
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->dontSeeResponseContainsJson(['reported' => ['id' => $this->reportGroupAdmin['id']]]);
    }

    public function dontSeeReportAboutFoodsharerReporterNotInRegion(ApiTester $I): void
    {
        $I->login($this->reportGroupAdmin['email']);
        $I->addReport($this->reportGroupAdmin['id'], $this->foodsharer['id']);
        $I->sendGET($I->apiReportListForRegion($this->region['id']));
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->dontSeeResponseContainsJson(['reported' => ['id' => $this->foodsharer['id']]]);
    }

    public function ArbitrationAdminSeesReportAboutReportAdmin(ApiTester $I): void
    {
        $I->login($this->arbitrationGroupAdmin['email']);
        $I->addReport($this->foodsharer['id'], $this->reportGroupAdmin['id']);
        $I->sendGET($I->apiReportListForRegion($this->region['id']));
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseContainsJson(['reported' => ['id' => $this->reportGroupAdmin['id']], 'reporter' => ['id' => $this->foodsharer['id']]]);
    }

    public function reportWithConfirmationFlagSendsMailToReporter(ApiTester $I): void
    {
        // Confirmation mail for the reporter, sent by default (#2667)
        $I->deleteAllMails();
        $reported = $I->createFoodsaver(null, ['bezirk_id' => $this->region['id']]);

        $I->login($this->foodsaver['email']);
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPost('api/users/' . $reported['id'] . '/reports', [
            'reason' => 1,
            'message' => 'ThisIsATestReportMessage',
        ]);
        $I->seeResponseCodeIs(HttpCode::OK);

        $I->expectNumMails(1, 10);
        $mail = $I->getMails()[0];
        $I->assertStringContainsString('Bestätigung deiner Meldung', $mail->subject);
        $I->assertContainsEquals($this->foodsaver['email'], array_map(fn ($value): string => $value->address, $mail->to));
        $I->assertStringContainsString('ThisIsATestReportMessage', $mail->html);
        $I->assertStringContainsString('Meldegruppe', $mail->html);
        // replies go to the group that handles the report, not to the no-reply sender
        $I->assertContainsEquals(
            'region-' . $this->reportGroup['id'] . '@' . PLATFORM_MAILBOX_HOST,
            array_map(fn ($value): string => $value->address, $mail->replyTo)
        );

        // The bell for the report group admins is unchanged
        $I->seeInDatabase('fs_foodsaver_has_bell', ['foodsaver_id' => $this->reportGroupAdmin['id']]);
    }

    public function reportWithConfirmationDisabledSendsNoMail(ApiTester $I): void
    {
        $I->deleteAllMails();
        $reported = $I->createFoodsaver(null, ['bezirk_id' => $this->region['id']]);

        $I->login($this->foodsaver['email']);
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPost('api/users/' . $reported['id'] . '/reports', [
            'reason' => 1,
            'message' => 'ThisIsATestReportMessage',
            'sendConfirmationMail' => false,
        ]);
        $I->seeResponseCodeIs(HttpCode::OK);

        // Give the async mail queue time to deliver a mail that must not arrive
        sleep(5);
        $I->expectNumMails(0, 0);

        // The bell for the report group admins is unchanged
        $I->seeInDatabase('fs_foodsaver_has_bell', ['foodsaver_id' => $this->reportGroupAdmin['id']]);
    }

    public function canClearConsequenceOfReport(ApiTester $I): void
    {
        $report = $I->addReport($this->foodsharer['id'], $this->foodsaver['id']);
        $I->updateInDatabase('fs_report', ['consequence' => 'reports.consequences.warning'], ['id' => $report['id']]);

        $I->login($this->reportGroupAdmin['email']);
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPatch('api/reports/' . $report['id'], ['consequence' => null]);
        $I->seeResponseCodeIs(HttpCode::OK);

        $I->seeInDatabase('fs_report', ['id' => $report['id'], 'consequence' => null]);
    }

    public function updateWithoutConsequenceClearsConsequence(ApiTester $I): void
    {
        // The client always sends the complete editable state, so a missing consequence means it was removed
        $report = $I->addReport($this->foodsharer['id'], $this->foodsaver['id']);
        $I->updateInDatabase('fs_report', ['consequence' => 'reports.consequences.warning'], ['id' => $report['id']]);

        $I->login($this->reportGroupAdmin['email']);
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPatch('api/reports/' . $report['id'], ['status' => 'reports.statuses.completed']);
        $I->seeResponseCodeIs(HttpCode::OK);

        $I->seeInDatabase('fs_report', [
            'id' => $report['id'],
            'status' => 'reports.statuses.completed',
            'consequence' => null,
        ]);
    }

    public function canSetConsequenceOfReport(ApiTester $I): void
    {
        $report = $I->addReport($this->foodsharer['id'], $this->foodsaver['id']);

        $I->login($this->reportGroupAdmin['email']);
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPatch('api/reports/' . $report['id'], ['consequence' => 'reports.consequences.yellow_card']);
        $I->seeResponseCodeIs(HttpCode::OK);

        $I->seeInDatabase('fs_report', ['id' => $report['id'], 'consequence' => 'reports.consequences.yellow_card']);
    }

    public function canClearForumThreadOfReport(ApiTester $I): void
    {
        $report = $I->addReport($this->foodsharer['id'], $this->foodsaver['id']);
        $thread = $I->addForumThread($this->reportGroup['id'], $this->reportGroupAdmin['id']);
        $I->updateInDatabase('fs_report', ['forum_thread_id' => $thread['id']], ['id' => $report['id']]);

        $I->login($this->reportGroupAdmin['email']);
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPatch('api/reports/' . $report['id'], ['forumThreadId' => null]);
        $I->seeResponseCodeIs(HttpCode::OK);

        $I->seeInDatabase('fs_report', ['id' => $report['id'], 'forum_thread_id' => null]);
    }

    public function canSetForumThreadOfReport(ApiTester $I): void
    {
        $report = $I->addReport($this->foodsharer['id'], $this->foodsaver['id']);
        $thread = $I->addForumThread($this->reportGroup['id'], $this->reportGroupAdmin['id']);

        $I->login($this->reportGroupAdmin['email']);
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPatch('api/reports/' . $report['id'], ['forumThreadId' => $thread['id']]);
        $I->seeResponseCodeIs(HttpCode::OK);

        $I->seeInDatabase('fs_report', ['id' => $report['id'], 'forum_thread_id' => $thread['id']]);
    }

    public function canClearReminderOfReport(ApiTester $I): void
    {
        $report = $I->addReport($this->foodsharer['id'], $this->foodsaver['id']);
        $I->updateInDatabase('fs_report', ['reminder_at' => '2030-01-01 10:00:00'], ['id' => $report['id']]);

        $I->login($this->reportGroupAdmin['email']);
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPatch('api/reports/' . $report['id'], ['reminderAt' => null]);
        $I->seeResponseCodeIs(HttpCode::OK);

        $I->seeInDatabase('fs_report', ['id' => $report['id'], 'reminder_at' => null]);
    }

    public function updateKeepsTheSentFlagOfAnUnchangedReminder(ApiTester $I): void
    {
        // The client sends the reminder of the loaded report back in every update, so an
        // unrelated change must not send a reminder that is already due a second time
        $report = $I->addReport($this->foodsharer['id'], $this->foodsaver['id']);
        $I->updateInDatabase('fs_report', ['reminder_at' => '2020-01-01 10:00:00', 'reminder_sent' => 1], ['id' => $report['id']]);

        $I->login($this->reportGroupAdmin['email']);
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPatch('api/reports/' . $report['id'], [
            'status' => 'reports.statuses.completed',
            'reminderAt' => '2020-01-01T10:00:00',
        ]);
        $I->seeResponseCodeIs(HttpCode::OK);

        $I->seeInDatabase('fs_report', [
            'id' => $report['id'],
            'reminder_at' => '2020-01-01 10:00:00',
            'reminder_sent' => 1,
        ]);
    }

    public function changingTheReminderResetsTheSentFlag(ApiTester $I): void
    {
        $report = $I->addReport($this->foodsharer['id'], $this->foodsaver['id']);
        $I->updateInDatabase('fs_report', ['reminder_at' => '2020-01-01 10:00:00', 'reminder_sent' => 1], ['id' => $report['id']]);

        $I->login($this->reportGroupAdmin['email']);
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPatch('api/reports/' . $report['id'], ['reminderAt' => '2030-01-01T10:00:00']);
        $I->seeResponseCodeIs(HttpCode::OK);

        $I->seeInDatabase('fs_report', [
            'id' => $report['id'],
            'reminder_at' => '2030-01-01 10:00:00',
            'reminder_sent' => 0,
        ]);
    }

    public function foodsaverCannotAccessReports(ApiTester $I): void
    {
        $I->login($this->foodsaver['email']);
        $I->sendGET($I->apiReportListForRegion($this->region['id']));
        $I->seeResponseCodeIs(HttpCode::FORBIDDEN);
    }

    public function foodsharerCannotAccessReports(ApiTester $I): void
    {
        $I->login($this->foodsharer['email']);
        $I->sendGET($I->apiReportListForRegion($this->region['id']));
        $I->seeResponseCodeIs(HttpCode::FORBIDDEN);
    }
}
