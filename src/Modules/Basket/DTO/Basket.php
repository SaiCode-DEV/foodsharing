<?php

declare(strict_types=1);

namespace Foodsharing\Modules\Basket\DTO;

use JMS\Serializer\Annotation\Type;
use Symfony\Component\Validator\Constraints as Assert;

class Basket
{
    public ?int $id = null;
    #[Assert\NotBlank]
    #[Assert\Type('string')]
    public string $description;
    public ?string $imageUrl = null;
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
    public int $weightInGrams;
}
