<?php

namespace Foodsharing\Modules\Foodsaver;

use DateTime;

class RegionGroupMemberEntry
{
    public int $id = 0;

    public ?string $name = null;

    public ?string $lastName = null;

    public ?string $avatar = null;

    public bool $isSleeping = false;

    public ?int $role = null;

    public ?string $lastPassDate = null;

    public ?DateTime $lastActivity = null;

    public bool $isAdminOrAmbassadorOfRegion = false;

    public ?bool $isVerified = null;

    public ?bool $isHomeRegion = null;

    public static function create(
        int $id,
        ?string $name,
        ?string $avatar,
        bool $isSleeping,
        bool $isAdminOrAmbassadorOfRegion): RegionGroupMemberEntry
    {
        $p = new RegionGroupMemberEntry();
        $p->id = $id;
        $p->name = $name;
        $p->avatar = $avatar;
        $p->isSleeping = $isSleeping;
        $p->isAdminOrAmbassadorOfRegion = $isAdminOrAmbassadorOfRegion;

        return $p;
    }
}
