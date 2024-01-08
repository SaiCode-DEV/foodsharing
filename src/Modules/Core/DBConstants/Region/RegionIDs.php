<?php

namespace Foodsharing\Modules\Core\DBConstants\Region;

class RegionIDs
{
    // upper level holding groups
    public const ROOT = 0;
    public const GLOBAL_WORKING_GROUPS = 392;
    public const EUROPE_WELCOME_TEAM = 813;
    public const EUROPE = 741; // second level from top. First selectable level

    // workgroups with special permissions:
    public const NEWSLETTER_WORK_GROUP = 331;
    public const QUIZ_AND_REGISTRATION_WORK_GROUP = 341;
    public const PR_PARTNER_AND_TEAM_WORK_GROUP = 1811;
    public const PR_START_PAGE = 2287;
    public const EUROPE_REPORT_TEAM = 432;
    public const CREATING_WORK_GROUPS_WORK_GROUP = 1701;
    public const IT_SUPPORT_GROUP = 387;
    public const IT_AND_SOFTWARE_DEVELOPMENT_GROUP = 329;

    public const EDITORIAL_GROUP = 327;
    public const BOT_WELCOME_TEAM = 328;
    public const STORE_CHAIN_GROUP = 332;

    // region and ambassador groups
    public const EUROPE_BOT_GROUP = 881;
    public const AUSTRIA = 63;
    public const AUSTRIA_BOT_GROUP = 761;
    public const SWITZERLAND = 106;
    public const SWITZERLAND_BOT_GROUP = 1763;
    public const VOTING_ADMIN_GROUP = 3871;
    public const ELECTION_ADMIN_GROUP = 5444;
    public const WELCOME_TEAM_ADMIN_GROUP = 4642;
    public const FSP_TEAM_ADMIN_GROUP = 4647;
    public const STORE_COORDINATION_TEAM_ADMIN_GROUP = 4648;
    public const REPORT_TEAM_ADMIN_GROUP = 4649;
    public const MEDIATION_TEAM_ADMIN_GROUP = 4650;
    public const ARBITRATION_TEAM_ADMIN_GROUP = 4651;
    public const FSMANAGEMENT_TEAM_ADMIN_GROUP = 4652;
    public const PR_TEAM_ADMIN_GROUP = 4653;
    public const MODERATION_TEAM_ADMIN_GROUP = 4654;
    public const BOARD_ADMIN_GROUP = 3875;
    public const ORGA_COORDINATION_GROUP = 3818;

    // groups used for displaying team page:
    public const TEAM_BOARD_MEMBER = 1373;
    public const TEAM_ALUMNI_MEMBER = 1564;
    public const TEAM_ADMINISTRATION_MEMBER = 1565;
    public const WORKGROUP_ADMIN_CREATION_GROUP = 1701;

    // Testregions
    public const TESTREGION_MASTER = 260;
    public const TESTREGION_1 = 343;
    public const TESTREGION_2 = 3113;

    // countries
    public const GERMANY = 1;

    public static function hasSpecialPermission(int $regionId): bool
    {
        return in_array($regionId, [
            self::NEWSLETTER_WORK_GROUP, self::QUIZ_AND_REGISTRATION_WORK_GROUP,
            self::PR_PARTNER_AND_TEAM_WORK_GROUP, self::PR_START_PAGE,
            self::EUROPE_REPORT_TEAM, self::IT_SUPPORT_GROUP, self::IT_AND_SOFTWARE_DEVELOPMENT_GROUP,
            self::EDITORIAL_GROUP, self::STORE_CHAIN_GROUP
        ]);
    }

    public static function getTestRegions(): array
    {
        return [
            self::TESTREGION_MASTER,
            self::TESTREGION_1,
            self::TESTREGION_2
        ];
    }
}
