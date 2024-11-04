<?php

namespace Foodsharing\RestApi\Models\Content;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

class ContentEntry
{
    #[OA\Property(
        description: 'A unique name for the entry that is not shown on the website',
        type: 'string',
        example: 'news-from-it'
    )]
    #[Assert\NotBlank]
    public readonly string $name;

    #[OA\Property(
        description: 'Human readable title of the content entry',
        type: 'string',
        example: 'Aktuelle Fehler und Störungen'
    )]
    #[Assert\NotBlank]
    public readonly string $title;

    #[OA\Property(
        description: 'The body of this content entry. This is usually HTML or markdown text.',
        type: 'string',
        example: 'Lorem ipsum'
    )]
    public readonly string $body;

    public function __construct(string $name, string $title, string $body)
    {
        $this->name = $name;
        $this->title = $title;
        $this->body = $body;
    }
}
