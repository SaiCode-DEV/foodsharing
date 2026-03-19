<?php

namespace Foodsharing\RestApi\Models\Forum;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

#[OA\Schema(description: 'Data for editing an existing forum post')]
class EditPostData
{
    #[OA\Property(description: 'Text of the post', example: 'Updated content of a forum post')]
    #[Assert\NotBlank]
    public string $body;
}
