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

    protected function __construct(array $data)
    {
        parent::__construct($data);
        $this->isAdminOrAmbassadorOfRegion = $data['isAdminOrAmbassadorOfRegion'];
        $this->role = Role::from($data['role']);
        $this->lastPassDate = $data['last_pass'];
        $this->lastName = $data['lastname'];
        $this->lastActivity = ($data['last_activity'] === '0000-00-00 00:00:00') ? new DateTime($data['registration_date']) : new DateTime($data['last_activity']);
        $this->isVerified = $data['verified'];
        $this->isHomeRegion = (bool)$data['is_home_region'];
    }

    public static function createFromArray(array $data): UnitMemberForAdmin
    {
        return new self($data);
    }
}
