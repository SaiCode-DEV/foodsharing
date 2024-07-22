<?php

namespace Foodsharing\Modules\Report;

use Doctrine\DBAL\Query\QueryBuilder;
use Foodsharing\Modules\Core\BaseGateway;

class ReportGateway extends BaseGateway
{
    /* Reporttype: 1: Other (see list ReportView for list of possible reasons, they are all mapped to 1...), 2: missed pickup */

    public function addBetriebReport($reportedId, $reporterId, $reasonId, $reason, $message, $storeId = 0): int
    {
        return $this->db->insert(
            'fs_report',
            [
                'foodsaver_id' => (int)$reportedId,
                'reporter_id' => (int)$reporterId,
                'reporttype' => (int)$reasonId,
                'betrieb_id' => (int)$storeId,
                'time' => date('Y-m-d H:i:s'),
                'committed' => 0,
                'msg' => strip_tags((string)$message),
                'tvalue' => strip_tags((string)$reason),
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
                'r.`time`',
                'r.`betrieb_id`',
                's.`name` as betrieb_name',
                'UNIX_TIMESTAMP(r.`time`) AS time_ts',

                'fs.id AS fs_id',
                'fs.name AS fs_name',
                'fs.nachname AS fs_nachname',
                'fs.photo AS fs_photo',
                'fs.email AS fs_email',

                'rp.id AS rp_id',
                'rp.name AS rp_name',
                'rp.nachname AS rp_nachname',
                'rp.photo AS rp_photo',
                'rp.email AS rp_email',
                'b.name AS b_name')
            ->leftJoin('r', 'fs_foodsaver', 'fs', 'r.foodsaver_id = fs.id')
            ->leftJoin('r', 'fs_foodsaver', 'rp', 'r.reporter_id = rp.id')
            ->leftJoin('r', 'fs_bezirk', 'b', 'fs.bezirk_id = b.id')
            ->leftJoin('r', 'fs_betrieb', 's', 'r.betrieb_id = s.id')
            ->orderBy('r.time', 'DESC');
    }

    public function getReportsByUser(int $userId): array
    {
        $query = $this->reportSelectDbal();
        $query->andWhere($query->expr()->eq('r.foodsaver_id', $userId));

        // restrict access only to new reports to avoid social conflicts from old entries
        $query->andWhere('time >= \'2021-01-01\'');

        return $query->fetchAllAssociative();
    }

    public function getReportsByReporteeRegions(int $regionId, ?array $excludeReportsWithUsers, ?array $onlyReportsWithUsers = null)
    {
        $query = $this->reportSelectDbal();
        $query->andWhere($query->expr()->eq('fs.bezirk_id', $regionId));

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

        return $query->fetchAllAssociative();
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
}
