<?php

namespace Foodsharing\Modules\Core\DBConstants\Region;

class RegionIDs
{
    // upper level holding groups
    final public const int ROOT = 0;
    final public const int GLOBAL_WORKING_GROUPS = 392;
    final public const int EUROPE_WELCOME_TEAM = 813;
    final public const int EUROPE = 741; // second level from top. First selectable level
    final public const int FOODSHARING_ON_FESTIVALS = 1432;

    // workgroups with special permissions:
    final public const int QUIZ_AND_REGISTRATION_WORK_GROUP = 341;
    final public const int QUIZ_GROUP_FR = 6045;
    final public const int NEW_QUIZZES_WORK_GROUP = 1063;
    final public const int PR_PARTNER_AND_TEAM_WORK_GROUP = 1811;
    final public const int PR_START_PAGE = 2287;
    final public const int CREATING_WORK_GROUPS_WORK_GROUP = 1701;
    final public const int IT_SUPPORT_GROUP = 387;
    final public const int IT_AND_SOFTWARE_DEVELOPMENT_GROUP = 329;
    final public const int PRODUCT_TEAM = 2296;
    final public const int OAUTH_CLIENT_ADMINISTRATION_WORK_GROUP = 6704;

    final public const int EDITORIAL_GROUP = 327;
    final public const int BOT_WELCOME_TEAM = 328;
    final public const int STORE_CHAIN_GROUP = 332;
    final public const int STORE_CHAIN_GROUP_SWITZERLAND = 1004;
    final public const int STORE_CHAIN_GROUP_AUSTRIA = 858;
    final public const int HYGIENE_GROUP = 1686;
    final public const int POLITICAL_CAMPAIGNS = 1880;

    // region and ambassador groups
    final public const int EUROPE_BOT_GROUP = 881;
    final public const int AUSTRIA = 63;
    final public const int AUSTRIA_BOT_GROUP = 761;
    final public const int SWITZERLAND = 106;
    final public const int SWITZERLAND_BOT_GROUP = 1763;
    final public const int VOTING_ADMIN_GROUP = 3871;
    final public const int ELECTION_ADMIN_GROUP = 5444;
    final public const int WELCOME_TEAM_ADMIN_GROUP = 4642;
    final public const int FSP_TEAM_ADMIN_GROUP = 4647;
    final public const int STORE_COORDINATION_TEAM_ADMIN_GROUP = 4648;
    final public const int REPORT_TEAM_ADMIN_GROUP = 4649;
    final public const int MEDIATION_TEAM_ADMIN_GROUP = 4650;
    final public const int ARBITRATION_TEAM_ADMIN_GROUP = 4651;
    final public const int FSMANAGEMENT_TEAM_ADMIN_GROUP = 4652;
    final public const int PR_TEAM_ADMIN_GROUP = 4653;
    final public const int MODERATION_TEAM_ADMIN_GROUP = 4654;
    final public const int BOARD_ADMIN_GROUP = 3875;
    final public const int ORGA_COORDINATION_GROUP = 3818;

    // groups used for displaying team page:
    final public const int TEAM_BOARD_MEMBER = 1373;
    final public const int TEAM_ALUMNI_MEMBER = 1564;
    final public const int TEAM_ADMINISTRATION_MEMBER = 1565;
    final public const int WORKGROUP_ADMIN_CREATION_GROUP = 1701;
    final public const int ORGA_TEAM_ARCHIVE = 258;

    // Testregions
    final public const int TESTREGION_MASTER = 260;
    final public const int TESTREGION_1 = 343;
    final public const int TESTREGION_2 = 3113;

    // countries
    final public const int GERMANY = 1;

    public static function hasSpecialPermission(int $regionId): bool
    {
        return in_array($regionId, [
            self::QUIZ_AND_REGISTRATION_WORK_GROUP,
            self::PR_PARTNER_AND_TEAM_WORK_GROUP, self::PR_START_PAGE,
            self::IT_SUPPORT_GROUP, self::EDITORIAL_GROUP, self::STORE_CHAIN_GROUP,
            self::PRODUCT_TEAM
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

    public static function isChainsGroup(int $regionId): bool
    {
        return in_array($regionId, [
            self::STORE_CHAIN_GROUP,
            self::STORE_CHAIN_GROUP_AUSTRIA,
            self::STORE_CHAIN_GROUP_SWITZERLAND,
        ]);
    }
}
