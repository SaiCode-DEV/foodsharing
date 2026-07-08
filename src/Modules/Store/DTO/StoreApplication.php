<?php

namespace Foodsharing\Modules\Store\DTO;

use Carbon\Carbon;
use DateTime;
use Foodsharing\Modules\Foodsaver\Profile;

class StoreApplication
{
    public Profile $user;
    public string $firstName;
    public bool $verified;

    /**
     * @var int|null The distance in kilometers to the store.
     * null if the fetching user is not allowed to access distance data.
     * -1 if the applicant has no valid address.
     */
    public ?int $distanceInKm = null;
    public ?DateTime $date = null;
    public ?string $message = null;

    public static function createFromArray(array $data)
    {
        $application = new self();
        $application->firstName = $data['name'];
        $application->user = new Profile(
            $data['id'],
            $data['name'] . ' ' . $data['nachname'],
            $data['photo'],
            (bool)$data['is_sleeping'],
        );
        $application->verified = (bool)$data['verified'];
        if (!is_null($data['distance'])) {
            $application->distanceInKm = $data['distance'] < 1 ? 0 : round($data['distance']);
        }
        $application->date = is_null($data['date_activity']) ? null : new Carbon($data['date_activity']);
        $application->message = $data['content'] ?: null;

        return $application;
    }
}
