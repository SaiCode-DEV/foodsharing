<?php

declare(strict_types=1);

namespace Foodsharing\Modules\Quiz\DTO;

use Foodsharing\Modules\Core\DBConstants\Quiz\AnswerRating;
use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

class Answer
{
    #[OA\Property(example: 1)]
    #[Assert\IsNull]
    public ?int $id;

    #[OA\Property(example: 'Take every thing I see with me.')]
    #[Assert\NotBlank]
    public string $text;

    #[OA\Property(example: 'You should not do that. Obviously.')]
    #[Assert\NotBlank]
    public ?string $explanation;

    #[OA\Property(example: 0)]
    #[Assert\NotNull]
    public ?AnswerRating $answerRating;

    public static function createFromArray(array $data): Answer
    {
        $result = new Answer();
        $result->id = $data['id'];
        $result->text = $data['text'];
        if (isset($data['explanation'])) {
            $result->explanation = $data['explanation'];
        } if (isset($data['right'])) {
            $result->answerRating = AnswerRating::from($data['right']);
        }

        return $result;
    }
}
