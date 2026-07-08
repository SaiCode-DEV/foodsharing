<?php

namespace Foodsharing\Modules\Report;

use Carbon\Carbon;
use Doctrine\DBAL\Query\QueryBuilder;
use Foodsharing\Modules\Core\BaseGateway;
use Foodsharing\Modules\Core\DBConstants\Report\ReportType;
use Foodsharing\Modules\Report\DTO\AddReportData;
use Foodsharing\Modules\Report\DTO\ProfileWithMail;
use Foodsharing\Modules\Report\DTO\ReportForListView;
use Foodsharing\Modules\Store\DTO\MinimalStoreIdentifier;

class ReportGateway extends BaseGateway
{
    public function addBetriebReport(int $reportedId, int $reporterId, AddReportData $reportData, string $reasonName): int
    {
        return $this->db->insert(
            'fs_report',
            [
                'foodsaver_id' => $reportedId,
                'reporter_id' => $reporterId,
                'reporttype' => ReportType::LOCAL->value,
                'report_reason_id' => $reportData->reason->value,
                'betrieb_id' => $reportData->storeId ?? 0,
                'time' => date('Y-m-d H:i:s'),
                'committed' => 0,
                'msg' => strip_tags($reportData->message),
                'tvalue' => strip_tags($reasonName),
            ]
        );
    }

    private function reportSelectDbal(): QueryBuilder
    {
        return $this->db->builder()
            ->from('fs_report', 'r')
            ->select(
                'r.id',
                'r.`msg`',
                'r.`tvalue`',
                'r.`reporttype`',
                'r.`report_reason_id`',
                'r.`time`',
                'r.`betrieb_id`',
                's.`name` as betrieb_name',
                'UNIX_TIMESTAMP(r.`time`) AS time_ts',

                'fs.id AS fs_id',
                'fs.name AS fs_name',
                'fs.nachname AS fs_last_name',
                'fs.photo AS fs_photo',
                'fs.email AS fs_email',

                'rp.id AS rp_id',
                'rp.name AS rp_name',
                'rp.nachname AS rp_last_name',
                'rp.photo AS rp_photo',
                'rp.email AS rp_email',
                'b.name AS b_name')
            ->leftJoin('r', 'fs_foodsaver', 'fs', 'r.foodsaver_id = fs.id')
            ->leftJoin('r', 'fs_foodsaver', 'rp', 'r.reporter_id = rp.id')
            ->leftJoin('r', 'fs_bezirk', 'b', 'fs.bezirk_id = b.id')
            ->leftJoin('r', 'fs_betrieb', 's', 'r.betrieb_id = s.id')
            ->orderBy('r.time', 'DESC');
    }

    /**
     * @return ReportForListView[]
     */
    public function getReportsByUser(int $userId): array
    {
        $query = $this->reportSelectDbal();
        $query->andWhere($query->expr()->eq('r.foodsaver_id', (string)$userId));

        $reports = $query->fetchAllAssociative();

        return array_map(fn ($report) => $this->createReportForListView($report), $reports);
    }

    /**
     * @return ReportForListView[]
     */
    public function getReportsByReporteeRegions(int $regionId, ?array $excludeReportsWithUsers, ?array $onlyReportsWithUsers = null)
    {
        $query = $this->reportSelectDbal();
        $query->andWhere($query->expr()->eq('fs.bezirk_id', (string)$regionId));
        $query->andWhere('r.reporttype = ' . ReportType::LOCAL->value);

        if (!empty($excludeReportsWithUsers)) {
            $query->andWhere($query->expr()->notIn('r.reporter_id', $excludeReportsWithUsers));
            $query->andWhere($query->expr()->notIn('r.foodsaver_id', $excludeReportsWithUsers));
        }
        if (!empty($onlyReportsWithUsers)) {
            $query->andWhere($query->expr()->or(
                $query->expr()->In('r.reporter_id', $onlyReportsWithUsers),
                $query->expr()->In('r.foodsaver_id', $onlyReportsWithUsers)
            ));
        }

        // restrict access only to new reports to avoid social conflicts from old entries
        $query->andWhere('time >= \'2021-01-01\'');

        $reports = $query->fetchAllAssociative();

        return array_map(fn ($report) => $this->createReportForListView($report), $reports);
    }

    public function getReportAffiliation(int $reportId): array
    {
        return $this->db->fetch('SELECT
                fs.id AS userId, fs.bezirk_id AS regionId
            FROM fs_report r
            JOIN fs_foodsaver fs ON fs.id = r.foodsaver_id
            WHERE r.id = ?
        ', [$reportId]);
    }

    public function deleteReport(int $reportId): void
    {
        $this->db->delete('fs_report', ['id' => $reportId]);
    }

    private function createReportForListView(array $report): ReportForListView
    {
        $reportForListView = new ReportForListView();
        $reportForListView->id = $report['id'];
        $reportForListView->message = $report['msg'];
        $reportForListView->reason = $report['tvalue'];
        $reportForListView->reportedAt = Carbon::parse($report['time']);
        $reportForListView->store = $report['betrieb_id'] ? MinimalStoreIdentifier::createFromArray($report, 'betrieb_') : null;
        $reportForListView->reporter = new ProfileWithMail($report['rp_id'], $report['rp_name'], $report['rp_photo'], null, $report['rp_email'], $report['rp_last_name']);
        $reportForListView->reported = new ProfileWithMail($report['fs_id'], $report['fs_name'], $report['fs_photo'], null, $report['fs_email'], $report['fs_last_name']);

        return $reportForListView;
    }
}
