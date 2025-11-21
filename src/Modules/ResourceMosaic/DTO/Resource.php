<?php

declare(strict_types=1);

namespace Foodsharing\Modules\ResourceMosaic\DTO;

use JMS\Serializer\Annotation\Type;
use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

class Resource
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 35)]
    #[OA\Property(example: 'Programming')]
    public string $name;

    #[OA\Property(example: 'Proficient in PHP and Vue')]
    public ?string $description;

    #[OA\Property(example: [1, 4, 6])]
    #[Assert\Unique()]
    #[Assert\All([
        new Assert\Positive(),
        new Assert\Type('int')
    ])]
    public array $categories;

    #[OA\Property(example: false)]
    public bool $isPrivate;

    #[OA\Property(example: 3)]
    #[Assert\Range(min: 1, max: 5)]
    public int $openness;

    #[Type('array<string>')]
    #[Assert\NotNull()]
    #[Assert\All([
        new Assert\NotBlank(),
        new Assert\Regex('/^\/api\/uploads\/[0-9a-f\-]+$/'),
    ])]
    public array $images;

    #[Assert\Positive()]
    public ?int $regionId;
}
