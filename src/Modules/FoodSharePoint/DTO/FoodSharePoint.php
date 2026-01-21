<?php

namespace Foodsharing\Modules\FoodSharePoint\DTO;

use DateTime;
use Foodsharing\Modules\Core\DBConstants\FoodSharePoint\ActivationStatus;
use Foodsharing\Modules\Core\DTO\Address;
use Foodsharing\Modules\Core\DTO\GeoLocation;
use Foodsharing\Modules\Foodsaver\Profile;

/** @psalm-consistent-constructor */
class FoodSharePoint
{
    public int $id;

    public string $name;

    public ?int $regionId;

    public ?string $picture;

    public ?ActivationStatus $status;

    public string $description;

    public Address $address;

    public GeoLocation $location;

    public DateTime $createdAt;

    public Profile $creator;

    public static function create(
        int $id, string $name, ?int $regionId, ?string $picture, ?ActivationStatus $status, string $description, Address $address,
        GeoLocation $location, DateTime $addedAt, Profile $addingUser
    ): static {
        $fsp = new static();
        $fsp->id = $id;
        $fsp->name = $name;
        $fsp->regionId = $regionId;
        $fsp->picture = $picture;
        $fsp->status = $status;
        $fsp->description = $description;
        $fsp->address = $address;
        $fsp->location = $location;
        $fsp->createdAt = $addedAt;
        $fsp->creator = $addingUser;

        return $fsp;
    }
}
