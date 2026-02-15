<?php

namespace Foodsharing\RestApi\Models\Forum;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

#[OA\Schema(description: 'Data for modifying an existing forum thread')]
class PatchThreadData
{
    #[OA\Property(description: 'If the thread should be pinned to the top of forum')]
    #[Assert\Range(min: -1, max: 10)]
    public ?int $stickiness = null;

    #[OA\Property(description: 'If the thread in a moderated forum should be activated')]
    public ?bool $isActive = null;

    #[OA\Property(description: 'If the thread is open or closed')]
    public ?int $status = null;

    #[OA\Property(description: 'Title of the thread', example: 'Forum thread title')]
    #[Assert\NotBlank(allowNull: true)]
    #[Assert\Length(max: 260)]
    public ?string $title = null;
}
