<?php

namespace Foodsharing\RestApi\Models\FoodSharePoint;

use DateTime;
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

    #[OA\Property(
        description: 'IDs of all users who are responsible for the food share point',
        type: 'array',
        items: new OA\Items(ref: new Model(type: Profile::class))),
    ]
    #[Assert\Count(min: 1)]
    #[Type('array<Foodsharing\Modules\Foodsaver\Profile>')]
    // TODO: rename into 'managers'
    public array $manager;

    #[Type('array<Foodsharing\Modules\Foodsaver\Profile>')]
    // TODO: rename into 'followers'
    public array $follower;

    public static function createFromArray(array $data): self
    {
        $foodSharePoint = new self();
        $foodSharePoint->id = $data['id'];
        $foodSharePoint->regionId = $data['bezirk_id'];
        $foodSharePoint->name = $data['name'];
        $foodSharePoint->picture = $data['picture'];
        $foodSharePoint->status = $data['status'];
        $foodSharePoint->description = $data['desc'];
        $foodSharePoint->address = $data['anschrift'];
        $foodSharePoint->postalCode = $data['plz'];
        $foodSharePoint->city = $data['ort'];
        $foodSharePoint->location = GeoLocation::createFromArray($data);
        $foodSharePoint->createdAt = $data['add_date'];
        $foodSharePoint->creator = new Profile([
            'id' => $data['add_foodsaver'],
            'name' => $data['fs_name'],
        ]);

        // TODO: add followers
        $foodSharePoint->follower = $data['followers'];
        $foodSharePoint->manager = $data['managers'];

        return $foodSharePoint;
    }
}
