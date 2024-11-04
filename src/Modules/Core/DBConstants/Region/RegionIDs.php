<?php

namespace Foodsharing\Modules\Core\DBConstants\Region;

class RegionIDs
{
    // upper level holding groups
    final public const ROOT = 0;
    final public const GLOBAL_WORKING_GROUPS = 392;
    final public const EUROPE_WELCOME_TEAM = 813;
    final public const EUROPE = 741; // second level from top. First selectable level
    final public const FOODSHARING_ON_FESTIVALS = 1432;

    // workgroups with special permissions:
    final public const NEWSLETTER_WORK_GROUP = 331;
    final public const QUIZ_AND_REGISTRATION_WORK_GROUP = 341;
    final public const QUIZ_GROUP_FR = 6045;
    final public const PR_PARTNER_AND_TEAM_WORK_GROUP = 1811;
    final public const PR_START_PAGE = 2287;
    final public const CREATING_WORK_GROUPS_WORK_GROUP = 1701;
    final public const IT_SUPPORT_GROUP = 387;
    final public const IT_AND_SOFTWARE_DEVELOPMENT_GROUP = 329;

    final public const EDITORIAL_GROUP = 327;
    final public const BOT_WELCOME_TEAM = 328;
    final public const STORE_CHAIN_GROUP = 332;
    final public const HYGIENE_GROUP = 1686;

    // region and ambassador groups
    final public const EUROPE_BOT_GROUP = 881;
    final public const AUSTRIA = 63;
    final public const AUSTRIA_BOT_GROUP = 761;
    final public const SWITZERLAND = 106;
    final public const SWITZERLAND_BOT_GROUP = 1763;
    final public const VOTING_ADMIN_GROUP = 3871;
    final public const ELECTION_ADMIN_GROUP = 5444;
    final public const WELCOME_TEAM_ADMIN_GROUP = 4642;
    final public const FSP_TEAM_ADMIN_GROUP = 4647;
    final public const STORE_COORDINATION_TEAM_ADMIN_GROUP = 4648;
    final public const REPORT_TEAM_ADMIN_GROUP = 4649;
    final public const MEDIATION_TEAM_ADMIN_GROUP = 4650;
    final public const ARBITRATION_TEAM_ADMIN_GROUP = 4651;
    final public const FSMANAGEMENT_TEAM_ADMIN_GROUP = 4652;
    final public const PR_TEAM_ADMIN_GROUP = 4653;
    final public const MODERATION_TEAM_ADMIN_GROUP = 4654;
    final public const BOARD_ADMIN_GROUP = 3875;
    final public const ORGA_COORDINATION_GROUP = 3818;

    // groups used for displaying team page:
    final public const TEAM_BOARD_MEMBER = 1373;
    final public const TEAM_ALUMNI_MEMBER = 1564;
    final public const TEAM_ADMINISTRATION_MEMBER = 1565;
    final public const WORKGROUP_ADMIN_CREATION_GROUP = 1701;

    // Testregions
    final public const TESTREGION_MASTER = 260;
    final public const TESTREGION_1 = 343;
    final public const TESTREGION_2 = 3113;

    // countries
    final public const GERMANY = 1;

    public static function hasSpecialPermission(int $regionId): bool
    {
        return in_array($regionId, [
            self::NEWSLETTER_WORK_GROUP, self::QUIZ_AND_REGISTRATION_WORK_GROUP,
            self::PR_PARTNER_AND_TEAM_WORK_GROUP, self::PR_START_PAGE,
            self::IT_SUPPORT_GROUP, self::EDITORIAL_GROUP, self::STORE_CHAIN_GROUP
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
