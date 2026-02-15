<?php

namespace Foodsharing\RestApi\Models\Forum;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

#[OA\Schema(description: 'Data for creating a new forum post')]
class CreatePostData
{
    #[OA\Property(description: 'Text of the post', example: 'Example content of a forum post')]
    #[Assert\NotBlank]
    public string $body;
}
