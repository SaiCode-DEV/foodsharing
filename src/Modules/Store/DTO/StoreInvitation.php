<?php

namespace Foodsharing\Modules\Store\DTO;

use Carbon\Carbon;
use DateTime;
use Foodsharing\Modules\Foodsaver\Profile;

class StoreInvitation
{
    public Profile $user;
    public bool $verified;
    public ?DateTime $date = null;
    public ?Profile $inviter = null;

    public static function createFromArray(array $data): StoreInvitation
    {
        $invitation = new self();
        $invitation->user = new Profile($data);
        $invitation->verified = (bool)$data['verified'];
        $invitation->date = is_null($data['date_activity']) ? null : new Carbon($data['date_activity']);
        $invitation->inviter = Profile::tryFrom($data, 'inviter_');

        return $invitation;
    }
}
