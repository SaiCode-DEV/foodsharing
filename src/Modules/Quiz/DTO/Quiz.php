<?php

declare(strict_types=1);

namespace Foodsharing\Modules\Quiz\DTO;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

class Quiz
{
    #[OA\Property(example: 1)]
    #[Assert\IsNull]
    public ?int $id = null;

    #[OA\Property(example: 'Foodsaver Quiz')]
    #[Assert\NotBlank]
    public string $name;

    #[OA\Property(example: 'This is the description of this quiz.')]
    #[Assert\NotBlank]
    public string $description;

    #[OA\Property(example: false)]
    #[Assert\IsFalse]
    public bool $isDescriptionHtmlEncoded = false;

    #[OA\Property(example: 2)]
    #[Assert\PositiveOrZero]
    public int $maxFailurePointsToSucceed;

    #[OA\Property(example: 10)]
    #[Assert\Positive]
    public int $questionCountTimed;

    #[OA\Property(example: 20)]
    #[Assert\AtLeastOneOf([
        new Assert\IsNull(),
        new Assert\Expression('this.questionCountTimed <= value', message: 'Must be at least the number of untimed questions.'),
    ])]
    public ?int $questionCountUntimed = null;

    public static function createFromArray(array $data): Quiz
    {
        $result = new Quiz();
        $result->id = $data['id'];
        $result->name = $data['name'];
        $result->description = $data['desc'];
        $result->isDescriptionHtmlEncoded = (bool)$data['is_desc_htmlentity_encoded'];
        $result->maxFailurePointsToSucceed = $data['maxfp'];
        $result->questionCountTimed = $data['questcount'];
        $result->questionCountUntimed = $data['questcount_untimed'];

        return $result;
    }
}
