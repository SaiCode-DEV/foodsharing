<?php

namespace Foodsharing\RestApi\Models\Voting;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

class VoteRequest
{
    #[OA\Property(description: 'A list that maps option indices to the vote values', type: 'object')]
    #[Assert\NotNull]
    #[Assert\Type('array')]
    #[Assert\Count(min: 1)]
    public array $options;
}
