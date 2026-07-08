<?php

namespace Foodsharing\Modules\Foodsaver;

use DateTime;
use Foodsharing\Modules\Core\DBConstants\Foodsaver\Role;

class UnitMemberForAdmin extends UnitMember
{
    public string $lastName;
    public Role $role;
    public ?string $lastPassDate = null;
    public DateTime $lastActivity;
    public bool $isVerified;
    public bool $isHomeRegion;

    public function __construct(
        int $id,
        string $name,
        ?string $avatar,
        ?bool $isSleeping,
        bool $isAdminOrAmbassadorOfRegion,
        string $lastName,
        Role $role,
        ?string $lastPassDate,
        DateTime $lastActivity,
        bool $isVerified,
        bool $isHomeRegion
    ) {
        parent::__construct($id, $name, $avatar, $isSleeping, $isAdminOrAmbassadorOfRegion);
        $this->lastName = $lastName;
        $this->role = $role;
        $this->lastPassDate = $lastPassDate;
        $this->lastActivity = $lastActivity;
        $this->isVerified = $isVerified;
        $this->isHomeRegion = $isHomeRegion;
    }
}
