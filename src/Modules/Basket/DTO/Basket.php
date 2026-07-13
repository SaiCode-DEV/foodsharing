<?php

declare(strict_types=1);

namespace Foodsharing\Modules\Basket\DTO;

use Carbon\Carbon;
use DateTime;
use Foodsharing\Modules\Core\DTO\GeoLocation;
use Foodsharing\Modules\Foodsaver\Profile;
use JMS\Serializer\Annotation\Type;
use Symfony\Component\Validator\Constraints as Assert;

class Basket
{
    public ?int $id = null;

    #[Assert\NotBlank]
    #[Assert\Type('string')]
    public string $description;

    #[Type('array<string>')]
    #[Assert\All([
        new Assert\NotBlank(),
        new Assert\Regex('/^\/api\/uploads\/[0-9a-f\-]+$/'),
    ])]
    #[Assert\NotNull]
    public ?array $pictures = null;

    #[Assert\Count(min: 1)]
    #[Type('array<int>')]
    public array $contactTypes = [];

    #[Assert\Expression('(2 not in this.contactTypes) || value')]
    #[Assert\Regex('/\+?[0-9\-\/ ]+/')]
    public ?string $mobile = null;

    #[Assert\Regex('/\+?[0-9\-\/ ]+/')]
    public ?string $telephone = null;

    #[Assert\NotNull]
    #[Assert\Type('float')]
    public float $lat;

    #[Assert\NotNull]
    #[Assert\Type('float')]
    public float $lon;

    #[Assert\Range(min: 1, max: 21, notInRangeMessage: 'Lifetime must be between {{ min }} and {{ max }} days.')]
    public ?int $lifeTimeInDays = null;

    #[Assert\NotNull]
    #[Assert\Range(min: 0, max: 100000, notInRangeMessage: 'Weight must be between {{ min }} and {{ max }} g.')]
    public ?int $weightInGrams = null;

    #[Assert\Blank]
    public ?int $status = null;

    // TODO merge with lat and lon
    #[Assert\Blank]
    public ?GeoLocation $location = null;

    #[Assert\Blank]
    public ?Profile $creator = null;

    #[Assert\Blank]
    public ?DateTime $created = null;

    #[Assert\Blank]
    public ?DateTime $updated = null;

    #[Assert\Blank]
    public ?DateTime $until = null;

    #[Assert\Blank]
    public ?int $requestCount = null;

    public static function createFromArray(array $data): Basket
    {
        $basket = new Basket();
        $basket->id = $data['id'];
        $basket->status = $data['status'];
        $basket->description = $data['description'];
        $picture = json_decode($data['picture'] ?? '', true);
        if (is_null($picture)) {
            $basket->pictures = [];
        } elseif (is_array($picture)) {
            $basket->pictures = $picture;
        } else {
            $basket->pictures = [$data['picture']];
        }
        $basket->contactTypes = array_map('intval', explode(':', (string)$data['contact_type']));
        $basket->location = new GeoLocation($data['lat'], $data['lon']);
        $basket->creator = new Profile($data['fs_id'], $data['fs_name'], $data['fs_photo'], (bool)$data['fs_is_sleeping']);
        if (in_array(2, $basket->contactTypes, true)) {
            $basket->telephone = $data['tel'];
            $basket->mobile = $data['handy'];
        }
        $basket->weightInGrams = (int)($data['weightInKg'] * 1000);
        $basket->created = isset($data['time']) ? new Carbon($data['time']) : null;
        $basket->until = isset($data['until']) ? new Carbon($data['until']) : null;
        $basket->updated = isset($data['update']) ? new Carbon($data['update']) : null;
        $basket->requestCount = $data['request_count'];

        return $basket;
    }
}
