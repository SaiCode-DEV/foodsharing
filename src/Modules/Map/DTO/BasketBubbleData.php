<?php

namespace Foodsharing\Modules\Map\DTO;

use DateTime;
use Foodsharing\Modules\Foodsaver\Profile;

/**
 * Contains all the data for a basket's bubble on the map.
 */
class BasketBubbleData
{
    /**
     * Id of the basket.
     */
    public int $id = -1;

    /**
     * Optional description text. Can be empty or null if the basket does not have a description.
     */
    public ?string $description = null;

    /**
     * Path to the basket's photo or null if the basket does not have a photo.
     */
    public array $pictures = [];

    /**
     * Date at which the basket was created. This is null if the current user does not have permission to see the
     * details.
     */
    public ?DateTime $createdAt = null;

    /**
     * The user who created the basket. This is null if the current user does not have permission to see the details.
     */
    public ?Profile $creator = null;

    public static function createFromArray(array $data): BasketBubbleData
    {
        $result = new BasketBubbleData();
        $result->id = $data['id'];
        $result->description = $data['description'];
        $picture = json_decode($data['picture'] ?? '', true);
        if (is_null($picture)) {
            $result->pictures = [];
        } elseif (is_array($picture)) {
            $result->pictures = $picture;
        } else {
            $result->pictures = [$data['picture']];
        }

        return $result;
    }
}
