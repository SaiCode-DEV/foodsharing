<?php

namespace Foodsharing\Modules\Store\DTO;

use Carbon\Carbon;
use DateTime;
use Foodsharing\Modules\Foodsaver\Profile;

class StoreApplication
{
    public Profile $user;
    public bool $verified;
    public ?int $distanceInKm = null;
    public ?DateTime $date;
    public ?string $message;

    public static function createFromArray(array $data)
    {
        $application = new self();
        $data['name'] = $data['name'] . ' ' . $data['nachname'];
        $application->user = new Profile($data);
        $application->verified = (bool)$data['verified'];
        if (!is_null($data['distance'])) {
            $application->distanceInKm = $data['distance'] < 1 ? 0 : round($data['distance']);
        }
        $application->date = is_null($data['date_activity']) ? null : new Carbon($data['date_activity']);
        $application->message = $data['content'] ? $data['content'] : null;

        return $application;
    }
}
