<?php

namespace Foodsharing\Modules\Foodsaver;

class UnitMember extends Profile
{
    public bool $isAdminOrAmbassadorOfRegion = false;

    public function __construct(
        int $id,
        string $name,
        ?string $avatar = null,
        ?bool $isSleeping = null,
        bool $isAdminOrAmbassadorOfRegion = false
    ) {
        parent::__construct($id, $name, $avatar, $isSleeping);
        $this->isAdminOrAmbassadorOfRegion = $isAdminOrAmbassadorOfRegion;
    }
}
