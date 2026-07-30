<?php

declare(strict_types=1);

namespace Foodsharing\Modules\Basket\DTO;

use DateTime;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;

class BasketForOwnerMenu
{
    #[OA\Property(example: 42)]
    public int $id;

    #[OA\Property(example: 'Einkaufskorb mit Obst und Gemüse')]
    public string $description;

    #[OA\Property(example: '/api/uploads/uuid', nullable: true)]
    public ?string $picture = null;

    #[OA\Property(description: 'When the basket was created', type: 'string', format: 'date-time')]
    public DateTime $createdAt;

    /**
     * @var BasketRequest[] $requests
     */
    #[OA\Property(
        description: 'Requests that have been made for this basket',
        type: 'array',
        items: new OA\Items(ref: new Model(type: BasketRequest::class))
    )]
    public array $requests = [];

    public static function create(int $id, string $description, ?string $picture, DateTime $createdAt): BasketForOwnerMenu
    {
        $basket = new BasketForOwnerMenu();
        $basket->id = $id;
        $basket->description = $description;
        $basket->picture = $picture;
        $basket->createdAt = $createdAt;

        return $basket;
    }
}
