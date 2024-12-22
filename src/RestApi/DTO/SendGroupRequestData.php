<?php

namespace Foodsharing\RestApi\DTO;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

#[OA\Schema(
    description: 'Class that represents the data of a join request, in a format in which it is sent to the client.',
    required: ['motivation', 'selectedTime']
)]
class SendGroupRequestData
{
    #[OA\Property(
        description: 'Motivation message for mail to group.',
        example: 'I like to join this workgroup to support foodsharing.'
    )]
    #[Assert\NotBlank]
    #[Assert\Type('string')]
    #[Assert\Length(max: 16_777_215)]
    public string $motivation;

    #[OA\Property(description: 'Ability message for mail to group.')]
    #[Assert\Type('string')]
    public string $ability;

    #[OA\Property(description: 'Experience message for mail to group.')]
    #[Assert\Type('string')]
    public string $experience;

    #[OA\Property(description: 'Selected time message for mail to group.')]
    #[Assert\NotBlank]
    #[Assert\Choice(choices: [1, 2, 3, 5])]
    #[Assert\Type('integer')]
    public int $selectedTime;
}
