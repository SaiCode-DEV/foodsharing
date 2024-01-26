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
    final public const APPLIED_FOR_TEAM = 0;
    final public const MEMBER = 1;
    final public const JUMPER = 2;
}
