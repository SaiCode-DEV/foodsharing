<?php

namespace Foodsharing\Modules\Store\DTO;

use DateTime;

// handy', 'telefon', 'last_fetch
class StoreTeamMember extends StoreStandbyTeamMember
{
    public ?string $handy;
    public ?string $telefon;
    public ?DateTime $lastFetch;
}
