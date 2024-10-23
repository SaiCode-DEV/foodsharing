<?php

declare(strict_types=1);

namespace Foodsharing\Modules\WallPost\DTO;

use Foodsharing\Modules\Foodsaver\Profile;
use JMS\Serializer\Annotation\Type;
use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

class WallPost
{
    #[OA\Property(example: 1, title: 'Unique identifier of the wall post')]
    public ?int $id;

    #[OA\Property(example: 'Hello world!', title: 'Text of the wall post')]
    #[Assert\Type('string')]
    #[Assert\NotNull()]
    public string $body;

    #[OA\Property(example: '2024-01-14 09:57:24', title: 'The posts time of creation')]
    public ?string $time;

    #[OA\Property(type: 'array', title: 'Pictures associated with the post',
        items: new OA\Items(type: 'string', example: '/api/uploads/bcb57ea1-ed73-4fde-849b-c88b11393690'),
        description: 'Image urls. Legacy images get returned as file identifier from which urls to differently sized images can be generated', )]
    #[Type('array<string>')]
    #[Assert\All([
        new Assert\NotBlank(),
        new Assert\Regex('/^\/api\/uploads\/[0-9a-f\-]+$/'),
    ])]
    public ?array $pictures = null;

    public ?Profile $author;

    public static function createFromArray(array $data): WallPost
    {
        $result = new WallPost();
        $result->id = $data['id'];
        $result->body = $data['body'];
        $result->time = $data['time'];

        if (!empty($data['attach'])) {
            $attach = json_decode($data['attach'], true);
            if (isset($attach['image'])) { // Legacy images
                $result->pictures = array_column($attach['image'], 'file');
            } elseif (isset($attach['images'])) {
                $result->pictures = $attach['images'];
            }
        }
        $result->author = new Profile($data, 'foodsaver_');

        return $result;
    }
}
