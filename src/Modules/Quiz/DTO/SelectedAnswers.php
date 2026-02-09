<?php

namespace Foodsharing\Modules\Quiz\DTO;

use JMS\Serializer\Annotation\Type;
use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

class SelectedAnswers
{
    /**
     * @var ?int[]
     */
    #[OA\Property(description: 'A list of answer IDs, or null if the question wasn\'t answered in time')]
    #[Type('array<int>|null')]
    #[Assert\AtLeastOneOf([
        new Assert\IsNull(),
        new Assert\All(new Assert\Positive()),
    ])]
    public ?array $ids = null;
}
