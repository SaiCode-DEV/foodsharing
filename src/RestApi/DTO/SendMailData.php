<?php

namespace Foodsharing\RestApi\DTO;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

#[OA\Schema(
    description: 'Class that represents the data of a mail to a group, in a format in which it is sent to the client.',
    required: ['message']
)]
class SendMailData
{
    #[Assert\NotBlank]
    #[Assert\Type('string')]
    #[Assert\Length(max: 16_777_215)]
    #[OA\Property(description: 'Message for mail to group.', example: 'Hey, I have a question. ....')]
    public string $message;
}
