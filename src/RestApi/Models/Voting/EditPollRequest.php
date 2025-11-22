<?php

namespace Foodsharing\RestApi\Models\Voting;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

class EditPollRequest
{
    #[OA\Property(
        description: 'New title of the poll, or null if the title should not be changed.',
        type: 'string',
        example: 'Test poll'
    )]
    #[Assert\NotBlank(allowNull: true)]
    public ?string $name = null;

    #[OA\Property(
        description: 'New description of the poll, or null if the description should not be changed.',
        type: 'string',
        example: 'This is a new description of the poll.'
    )]
    #[Assert\NotBlank(allowNull: true)]
    public ?string $description = null;

    #[OA\Property(
        description: 'A new list of options for the poll, or null if the options should not be changed',
        type: 'array<string>'
    )]
    #[Assert\Count(min: 1)]
    #[Assert\All(new Assert\NotBlank())]
    public ?array $options = null;

    #[OA\Property(
        description: 'Whether the options should be shuffled for voters.',
        type: 'boolean',
        example: true
    )]
    public bool $shuffleOptions = false;
}
