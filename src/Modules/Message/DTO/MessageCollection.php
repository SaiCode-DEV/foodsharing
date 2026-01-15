<?php

namespace Foodsharing\Modules\Message\DTO;

use Foodsharing\Modules\Foodsaver\Profile;
use Foodsharing\Modules\Message\Message;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;

class MessageCollection
{
    #[OA\Property(type: 'array', items: new OA\Items(ref: new Model(type: Message::class)))]
    public array $messages;

    #[OA\Property(type: 'array', items: new OA\Items(ref: new Model(type: Profile::class)))]
    public array $profiles;
}
