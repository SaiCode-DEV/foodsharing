<?php

declare(strict_types=1);

namespace Foodsharing\Modules\Event\DTO;

use Carbon\Carbon;
use DateTime;
use Foodsharing\Modules\Core\DBConstants\Event\EventType;
use Foodsharing\Modules\Core\DTO\GeoLocation;
use Foodsharing\Modules\Store\DTO\Address;
use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

class Event
{
    #[OA\Property(example: 1)]
    #[Assert\Positive]
    public int $id;

    #[OA\Property(example: 1)]
    #[Assert\PositiveOrZero]
    #[Assert\NotBlank]
    public int $regionId;

    #[OA\Property(example: 'Event Name')]
    #[Assert\NotBlank]
    public string $name;

    #[OA\Property(example: '2024-05-31T12:45:00Z')]
    #[Assert\NotBlank]
    public DateTime $startDate;

    #[OA\Property(example: '2024-05-31T15:00:00Z')]
    #[Assert\NotBlank]
    #[Assert\Expression('this.endDate > this.startDate', message: 'This value must be after startDate.')]
    public DateTime $endDate;

    #[OA\Property(example: 'Event Description')]
    #[Assert\NotBlank]
    public string $description;

    #[OA\Property(example: 1)]
    #[Assert\NotBlank]
    public EventType $type;

    #[Assert\Valid]
    #[Assert\When([
        'expression' => 'this.type == enum("Foodsharing\\\\Modules\\\\Core\\\\DBConstants\\\\Event\\\\EventType::OFFLINE")',
        'constraints' => [new Assert\NotNull()],
    ])]
    public ?Address $address = null;

    #[Assert\Valid]
    #[Assert\When([
        'expression' => 'this.type.value == enum("Foodsharing\\\\Modules\\\\Core\\\\DBConstants\\\\Event\\\\EventType::OFFLINE")',
        'constraints' => [new Assert\NotNull()],
    ])]
    public ?GeoLocation $location = null;

    #[Assert\When([
        'expression' => 'this.type == enum("Foodsharing\\\\Modules\\\\Core\\\\DBConstants\\\\Event\\\\EventType::OTHER")',
        'constraints' => [new Assert\NotBlank()],
    ])]
    public ?string $locationDetails = null;

    public static function createFromArray(array $data): Event
    {
        $result = new Event();
        $result->id = $data['id'] ?? 0;
        $result->regionId = $data['bezirk_id'];
        $result->name = $data['name'];
        $result->startDate = new Carbon($data['start']);
        $result->endDate = new Carbon($data['end']);
        $result->description = $data['description'];
        $result->type = EventType::from($data['online']);

        if ($result->type === EventType::OFFLINE) {
            if (!$data['street'] && !$data['city']) {
                // Needed for legacy events, that are saved the same as usual offline events, but with missing data
                $result->location = null;
                $result->address = null;
                $result->type = EventType::OTHER;
            } else {
                $result->address = Address::createFromArray($data);
                $result->location = GeoLocation::createFromArray($data);
            }
        }
        if ($result->type === EventType::OFFLINE || $result->type === EventType::OTHER) {
            $result->locationDetails = $data['location_details'];
        }

        return $result;
    }
}
