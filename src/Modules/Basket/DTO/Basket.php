<?php

declare(strict_types=1);

namespace Foodsharing\Modules\Basket\DTO;

use Foodsharing\Modules\Core\DTO\GeoLocation;
use Foodsharing\Modules\Foodsaver\Profile;
use JMS\Serializer\Annotation\Type;
use Symfony\Component\Validator\Constraints as Assert;

class Basket
{
    public ?int $id;

    #[Assert\NotBlank]
    #[Assert\Type('string')]
    public string $description;

    #[Type('array<string>')]
    #[Assert\All([
        new Assert\NotBlank(),
        new Assert\Regex('/^\/api\/uploads\/[0-9a-f\-]+$/'),
    ])]
    #[Assert\NotNull]
    public ?array $pictures;

    #[Assert\Count(min: 1)]
    #[Type('array<int>')]
    public array $contactTypes = [];

    #[Assert\Expression('(2 not in this.contactTypes) || value')]
    #[Assert\Regex('/\+?[0-9\-\/ ]+/')]
    public ?string $mobile;

    #[Assert\Regex('/\+?[0-9\-\/ ]+/')]
    public ?string $telephone;

    #[Assert\NotNull]
    #[Assert\Type('float')]
    public float $lat;

    #[Assert\NotNull]
    #[Assert\Type('float')]
    public float $lon;

    #[Assert\Range(min: 1, max: 21, notInRangeMessage: 'Lifetime must be between {{ min }} and {{ max }} days.')]
    public ?int $lifeTimeInDays;

    #[Assert\NotNull]
    #[Assert\Range(min: 0, max: 100000, notInRangeMessage: 'Weight must be between {{ min }} and {{ max }} g.')]
    public ?int $weightInGrams;

    #[Assert\Blank]
    public ?int $status;

    // TODO merge with lat and lon
    #[Assert\Blank]
    public ?GeoLocation $location;

    #[Assert\Blank]
    public ?Profile $creator;

    #[Assert\Blank]
    public ?int $created;

    #[Assert\Blank]
    public ?int $updated;

    #[Assert\Blank]
    public ?int $until;

    #[Assert\Blank]
    public ?int $requestCount;

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
        $basket->contactTypes = array_map('intval', explode(':', $data['contact_type']));
        $basket->location = GeoLocation::createFromArray($data);
        $basket->creator = new Profile($data, 'fs_');
        if (in_array(2, $basket->contactTypes, true)) {
            $basket->telephone = $data['tel'];
            $basket->mobile = $data['handy'];
        }
        $basket->weightInGrams = (int)($data['weightInKg'] * 1000);
        $basket->created = $data['time_ts'];
        $basket->updated = $data['update_ts'];
        $basket->until = $data['until_ts'];
        $basket->requestCount = $data['request_count'];

        return $basket;
    }
}
