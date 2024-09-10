<?php

namespace Foodsharing\Modules\Foodsaver;

class UnitMember extends Profile
{
    public bool $isAdminOrAmbassadorOfRegion = false;

    protected function __construct(array $data)
    {
        parent::__construct($data);
        $this->isAdminOrAmbassadorOfRegion = $data['isAdminOrAmbassadorOfRegion'];
    }

    public static function createFromArray(array $data): UnitMember
    {
        return new self($data);
    }
}
