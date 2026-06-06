<?php

namespace Foodsharing\RestApi\DTO;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

#[OA\Schema(
    description: 'Class that represents the data of a join request, in a format in which it is sent to the client.',
    required: ['application']
)]
class SendGroupRequestData
{
    #[OA\Property(
        description: 'Application message for mail to group.',
        example: 'I like to join this workgroup to support foodsharing.'
    )]
    #[Assert\NotBlank]
    #[Assert\Type('string')]
    #[Assert\Length(max: 16_777_215)]
    public string $application;
}
