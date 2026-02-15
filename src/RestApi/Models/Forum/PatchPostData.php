<?php

namespace Foodsharing\RestApi\Models\Forum;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

#[OA\Schema(description: 'Data for modifying an existing forum post')]
class PatchPostData
{
    #[OA\Property(description: 'Explanation why the post was hidden', example: 'Post contains spam')]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    public string $reason;
}
