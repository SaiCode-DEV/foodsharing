<?php

namespace Foodsharing\RestApi\Models;

use JMS\Serializer\Annotation\Type;
use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A general request body that contains a list of IDs (for profiles, bells, etc.).
 */
class IDList
{
    /**
     * @var int[]
     */
    #[OA\Property(description: 'A list of IDs')]
    #[Assert\NotBlank]
    #[Assert\All(new Assert\Positive())]
    #[Type('array<int>')]
    public array $ids;
}
