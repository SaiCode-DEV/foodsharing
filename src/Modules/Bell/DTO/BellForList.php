<?php

namespace Foodsharing\Modules\Bell\DTO;

use DateTime;
use OpenApi\Attributes as OA;

/**
 * A Data Transfer Object to contain all data of a bell to be displayed in the bell list in the frontend.
 */
class BellForList
{
    public int $id;

    #[OA\Property(example: 'event_post.many', description: 'translation key for the bell body')]
    public string $key;

    #[OA\Property(example: 'event_post_title', description: 'translation key for the bell title')]
    public string $title;

    #[OA\Property(example: 'event_post_title', description: 'The destination of the bell when clicked on')]
    public ?string $href;

    #[OA\Property(type: 'object', description: 'The variables used in the translations', additionalProperties: new OA\AdditionalProperties())]
    public array $payload;

    #[OA\Property(example: 'fas fa-calendar', description: 'CSS class of the bell\'s icon')]
    public ?string $icon;

    #[OA\Property(example: null, description: 'Relative URL to an image to be used as an icon.<br>Only one of $image and $icon are supported.')]
    public ?string $image;

    #[OA\Property(description: 'The time of the bell – usually the creation time, but some bells use different times for this attribute.')]
    public DateTime $createdAt;

    public bool $isCloseable;

    #[OA\Property(description: 'Whether the foodsharer, for whom the bell is displayed, has already clicked on it.')]
    public bool $isRead;
}
