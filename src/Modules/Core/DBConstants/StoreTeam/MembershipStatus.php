<?php

// table `fs_betrieb_team`

namespace Foodsharing\Modules\Core\DBConstants\StoreTeam;

/**
 * column `active`
 * membership states for foodsavers and foodsharers
 * INT(11)          NOT NULL DEFAULT '0',.
 */
class MembershipStatus
{
    final public const int APPLIED_FOR_TEAM = 0;
    final public const int MEMBER = 1;
    final public const int JUMPER = 2;
    final public const int INVITED = 3;
}
