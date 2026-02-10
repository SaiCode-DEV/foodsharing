<?php

namespace Foodsharing\Modules\Report\DTO;

enum ReportReason: int
{
    case LATE = 1;
    case NO_SHOW = 2;
    case CANCELLATION = 10;
    case SELLS = 15;
    case VIOLATED_B1_0 = 2010;
    case VIOLATED_B1_1 = 2011;
    case VIOLATED_B1_2 = 2012;
    case VIOLATED_B1_3 = 2013;
    case VIOLATED_B1_4 = 2014;
    case VIOLATED_B2 = 2020;
    case VIOLATED_B3_0 = 2030;
    case VIOLATED_B3_1 = 2031;
    case VIOLATED_B4_0 = 2040;
    case VIOLATED_B4_1 = 2041;
    case VIOLATED_B5 = 2050;
    case VIOLATED_B6_0 = 2060;
    case VIOLATED_B6_1 = 2061;
    case VIOLATED_B7 = 2070;
    case VIOLATED_B8 = 2080;
    case VIOLATED_B9 = 2090;
    case VIOLATED_B10 = 2100;
    case VIOLATED_B11 = 2110;
    case VIOLATED_B12 = 2120;
    case VIOLATED_B13 = 2130;
    case OTHER = 9999;

    public function getTextKey(): string
    {
        return match ($this) {
            self::LATE => 'profile.report.late',
            self::NO_SHOW => 'profile.report.noshow',
            self::CANCELLATION => 'profile.report.cancellation',
            self::SELLS => 'profile.report.sells',
            self::VIOLATED_B1_0 => 'profile.report.report_b1_0',
            self::VIOLATED_B1_1 => 'profile.report.report_b1_1',
            self::VIOLATED_B1_2 => 'profile.report.report_b1_2',
            self::VIOLATED_B1_3 => 'profile.report.report_b1_3',
            self::VIOLATED_B1_4 => 'profile.report.report_b1_4',
            self::VIOLATED_B2 => 'profile.report.report_b2',
            self::VIOLATED_B3_0 => 'profile.report.report_b3_0',
            self::VIOLATED_B3_1 => 'profile.report.report_b3_1',
            self::VIOLATED_B4_0 => 'profile.report.report_b4_0',
            self::VIOLATED_B4_1 => 'profile.report.report_b4_1',
            self::VIOLATED_B5 => 'profile.report.report_b5',
            self::VIOLATED_B6_0 => 'profile.report.report_b6_0',
            self::VIOLATED_B6_1 => 'profile.report.report_b6_1',
            self::VIOLATED_B7 => 'profile.report.report_b7',
            self::VIOLATED_B8 => 'profile.report.report_b8',
            self::VIOLATED_B9 => 'profile.report.report_b9',
            self::VIOLATED_B10 => 'profile.report.report_b10',
            self::VIOLATED_B11 => 'profile.report.report_b11',
            self::VIOLATED_B12 => 'profile.report.report_b12',
            self::VIOLATED_B13 => 'profile.report.report_b13',
            self::OTHER => 'profile.report.report_other',
        };
    }
}
