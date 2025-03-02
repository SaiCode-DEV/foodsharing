<?php

namespace Foodsharing\RestApi\Models\FoodSharePoint;

use Carbon\Carbon;
use DateTime;
use DateTimeZone;
use Foodsharing\Modules\Core\DTO\GeoLocation;
use Foodsharing\Modules\Foodsaver\Profile;
use JMS\Serializer\Annotation\Type;
use Nelmio\ApiDocBundle\Annotation\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Contains all the information that is needed for creating a new food share point.
 */
class FoodSharePointData extends FoodSharePointForCreation
{
    public int $id;

    public int $status;

    public DateTime $createdAt;

    public Profile $creator;

    public string $regionName;

    public int $followerCount;

    #[OA\Property(
        description: 'IDs of all users who are responsible for the food share point',
        type: 'array',
        items: new OA\Items(ref: new Model(type: Profile::class))),
    ]
    #[Assert\Count(min: 1)]
    #[Type('array<Foodsharing\Modules\Foodsaver\Profile>')]
    public array $managers;

    public static function createFromArray(array $data): self
    {
        $foodSharePoint = new self();
        $foodSharePoint->id = $data['food_share_point_id'];
        $foodSharePoint->regionId = $data['region_id'];
        $foodSharePoint->regionName = $data['region_name'];
        $foodSharePoint->name = $data['food_share_point_name'];
        $foodSharePoint->picture = $data['picture'];
        $foodSharePoint->status = $data['status'];
        $foodSharePoint->description = $data['desc'];
        $foodSharePoint->address = $data['anschrift'];
        $foodSharePoint->postalCode = $data['plz'];
        $foodSharePoint->city = $data['ort'];
        $foodSharePoint->location = GeoLocation::createFromArray([
            'lat' => $data['lat'],
            'lon' => $data['lon'],
        ]);
        $foodSharePoint->createdAt = Carbon::createFromTimestamp($data['add_date'], new DateTimeZone('Europe/Berlin'));
        $foodSharePoint->creator = new Profile($data, 'creator_');
        $foodSharePoint->followerCount = $data['follower_count'];

        return $foodSharePoint;
    }

    public function setManagers(array $managers): void
    {
        $this->managers = array_map(fn ($manager) => new Profile($manager), $managers);
    }
}
