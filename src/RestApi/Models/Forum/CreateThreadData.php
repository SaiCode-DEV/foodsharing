<?php

namespace Foodsharing\RestApi\Models\Forum;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

#[OA\Schema(description: 'Data for creating a new forum thread')]
class CreateThreadData
{
    #[OA\Property(description: 'Text of the post', example: 'Example content of a forum post')]
    #[Assert\NotBlank]
    public string $body;

    #[OA\Property(description: 'Title of the thread', example: 'Forum thread title')]
    #[Assert\NotBlank]
    #[Assert\Length(max: 260)]
    public string $title;

    #[OA\Property(description: 'If a notification e-mail should be sent to all members of the forum')]
    public bool $sendMail;
}
