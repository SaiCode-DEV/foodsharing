<?php

namespace Foodsharing\Modules\Report;

use Foodsharing\Lib\Session;
use Foodsharing\Modules\Bell\BellGateway;
use Foodsharing\Modules\Bell\DTO\Bell;
use Foodsharing\Modules\Core\DBConstants\Bell\BellType;
use Foodsharing\Modules\Core\DBConstants\Foodsaver\Role;
use Foodsharing\Modules\Core\DBConstants\Region\WorkgroupFunction;
use Foodsharing\Modules\Foodsaver\FoodsaverGateway;
use Foodsharing\Modules\Group\GroupFunctionGateway;
use Foodsharing\Modules\Group\GroupGateway;
use Foodsharing\Modules\Report\DTO\AddReportData;
use Foodsharing\Modules\Report\DTO\ReportForListView;
use Foodsharing\Utility\EmailHelper;
use Symfony\Contracts\Translation\TranslatorInterface;

class ReportTransactions
{
    public function __construct(
        private readonly ReportGateway $reportGateway,
        private readonly TranslatorInterface $translator,
        private readonly FoodsaverGateway $foodsaverGateway,
        private readonly BellGateway $bellGateway,
        private readonly GroupFunctionGateway $groupFunctionGateway,
        private readonly GroupGateway $groupGateway,
        private readonly Session $session,
        private readonly EmailHelper $emailHelper,
    ) {
    }

    public function addReport(int $reportedId, int $reporterId, AddReportData $reportData): void
    {
        $reasonName = $this->translator->trans($reportData->reason->getTextKey());

        $this->reportGateway->addBetriebReport($reportedId, $reporterId, $reportData, $reasonName);

        $reportedFs = $this->foodsaverGateway->getFoodsaverBasics($reportedId);
        $bellData = Bell::create(
            'new_report_title',
            'report_reason',
            'fas fa-people-arrows fa-fw',
            ['href' => '/report/region/' . $reportedFs['bezirk_id']],
            [
                'name' => $reportedFs['name'] . ' ' . $reportedFs['nachname'],
                'reason' => $reasonName
            ],
            BellType::createIdentifier(BellType::NEW_REPORT, $reportedId),
            true
        );

        $isForArbitration = false;
        $reportBellRecipients = $this->groupFunctionGateway->getFunctionGroupAdminsForRegion($reportedFs['bezirk_id'], WorkgroupFunction::REPORT);
        if (!empty($reportBellRecipients)) {
            if (in_array($reportedId, $reportBellRecipients) || in_array($reporterId, $reportBellRecipients)) {
                $reportBellRecipients = $this->groupFunctionGateway->getFunctionGroupAdminsForRegion($reportedFs['bezirk_id'], WorkgroupFunction::ARBITRATION);
                $isForArbitration = true;
            }
            $this->bellGateway->addBellForUsers($reportBellRecipients, $bellData);
        }

        if ($reportData->sendConfirmationMail) {
            $this->sendReportConfirmationMail($reporterId, $reportedFs, $reasonName, $reportData->message, $isForArbitration);
        }
    }

    /**
     * Sends a summary of the report to the reporter's private email address.
     */
    private function sendReportConfirmationMail(int $reporterId, array $reportedFs, string $reasonName, string $message, bool $isForArbitration): void
    {
        $reporter = $this->foodsaverGateway->getFoodsaverBasics($reporterId);
        $groupName = $this->translator->trans(
            $isForArbitration ? 'email_template.report_confirmation.group_arbitration' : 'email_template.report_confirmation.group_report'
        );
        $this->emailHelper->tplMail('report/confirmation', $this->foodsaverGateway->getEmailAddress($reporterId), [
            'name' => $reporter['name'],
            'reported_name' => $reportedFs['name'] . ' ' . $reportedFs['nachname'],
            'group' => $groupName,
            'reason' => $reasonName,
            'message' => $message,
        ], replyToEmail: $this->getResponsibleGroupMail($reportedFs['bezirk_id'], $isForArbitration));
    }

    /**
     * Mailbox address of the group that handles the report, so a reply reaches
     * the people who can answer it instead of the no-reply sender.
     */
    private function getResponsibleGroupMail(int $regionId, bool $isForArbitration): ?string
    {
        $groupId = $this->groupFunctionGateway->getRegionFunctionGroupId(
            $regionId,
            $isForArbitration ? WorkgroupFunction::ARBITRATION : WorkgroupFunction::REPORT
        );
        if ($groupId === null) {
            return null;
        }

        $mailboxName = $this->groupGateway->getGroupMailName($groupId);

        return $mailboxName ? $mailboxName . '@' . PLATFORM_MAILBOX_HOST : null;
    }

    /** @return ReportForListView[] */
    public function getReportsForRegion(int $regionId): array
    {
        $userId = $this->session->id();
        $excludedIds = [$userId];
        $includedIds = null;
        $reportAdmins = $this->groupFunctionGateway->getFunctionGroupAdminsForRegion($regionId, WorkgroupFunction::REPORT);
        $arbitrationAdmins = $this->groupFunctionGateway->getFunctionGroupAdminsForRegion($regionId, WorkgroupFunction::ARBITRATION);
        $isReportAdmin = in_array($userId, $reportAdmins);
        if ($isReportAdmin) {
            array_push($excludedIds, ...$reportAdmins);
        }
        if (in_array($userId, $arbitrationAdmins)) {
            array_push($excludedIds, ...$arbitrationAdmins);
        }
        if (!($isReportAdmin || $this->session->mayRole(Role::ORGA))) {
            $includedIds = $reportAdmins;
        }

        return $this->reportGateway->getReportsByReporteeRegions($regionId, $excludedIds, $includedIds);
    }

    public function getResponsibleRegionIdForReport(int $reportId): int
    {
        $affiliation = $this->reportGateway->getReportAffiliation($reportId);
        $baseRegionId = (int)$affiliation['regionId'];
        $reportedId = (int)$affiliation['userId'];
        $reporterId = (int)$affiliation['reporterId'];

        $reportGroupId = $this->groupFunctionGateway->getRegionFunctionGroupId($baseRegionId, WorkgroupFunction::REPORT);
        if ($reportGroupId !== null) {
            $reportAdmins = $this->groupFunctionGateway->getFsAdminIdsFromGroup($reportGroupId);
            if (in_array($reportedId, $reportAdmins, true) || in_array($reporterId, $reportAdmins, true)) {
                $arbitrationGroupId = $this->groupFunctionGateway->getRegionFunctionGroupId($baseRegionId, WorkgroupFunction::ARBITRATION);
                if ($arbitrationGroupId !== null) {
                    return $arbitrationGroupId;
                }

                return $baseRegionId;
            }

            return $reportGroupId;
        }

        return $baseRegionId;
    }
}
