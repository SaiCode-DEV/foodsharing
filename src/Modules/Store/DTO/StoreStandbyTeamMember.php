<?php

namespace Foodsharing\Modules\Store\DTO;

use DateTime;
use Foodsharing\Modules\Core\DBConstants\Foodsaver\Role;
use Foodsharing\Modules\Foodsaver\Profile;

/**
 * @property string $name The name of the team member.
 *
 * May include the last name or be just the first name, depending on the users permissions.
 */
class StoreStandbyTeamMember extends Profile
{
    public string $firstName;
    public Role $role;
    public bool $isVerified;
    public ?DateTime $hygieneCertificateUntil;
    public bool $isResponsible;
    public int $membershipStatus;
    public int $fetchCount;
    public ?DateTime $memberSince;
}
