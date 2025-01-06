<?php

declare(strict_types=1);

namespace Foodsharing\Modules\Quiz\DTO;

use Nelmio\ApiDocBundle\Annotation\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

class Question
{
    #[OA\Property(example: 1)]
    #[Assert\IsNull]
    public ?int $id = null;

    #[OA\Property(example: 'You are at a store for a pickup. What should you do?')]
    #[Assert\NotBlank]
    public string $text;

    #[OA\Property(example: 60)]
    #[Assert\NotNull]
    #[Assert\Positive]
    public ?int $durationInSeconds = null;

    #[OA\Property(example: 'https://wiki.foodsharing.de/some_page')]
    #[Assert\NotBlank]
    #[Assert\Regex('/^https:\/\/[\d\w]+\.[\d\w]+/')]
    public string $wikilink;

    #[OA\Property(example: 2)]
    #[Assert\PositiveOrZero]
    public int $failurePoints;

    #[OA\Property(example: false)]
    public bool $isMandatory;

    #[OA\Property(type: 'array', items: new OA\Items(ref: new Model(type: Answer::class)))]
    #[Assert\IsNull]
    public ?array $answers = null;

    #[Assert\IsNull]
    public ?int $commentCount = null;

    public static function createFromArray(array $data): Question
    {
        $result = new Question();
        $result->id = $data['id'];
        $result->text = $data['text'];
        $result->durationInSeconds = $data['duration'] ?? $data['durationInSeconds'];
        $result->wikilink = $data['wikilink'];
        $result->failurePoints = $data['fp'] ?? $data['failurePoints'];
        $result->isMandatory = boolval($data['is_mandatory'] ?? false);

        return $result;
    }
}
