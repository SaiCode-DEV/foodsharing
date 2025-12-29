<?php

declare(strict_types=1);

namespace Foodsharing\Modules\Buddy\DTO;

use Foodsharing\Modules\Foodsaver\Profile;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;

class BuddyList
{
    #[OA\Property(type: 'array', items: new OA\Items(ref: new Model(type: Profile::class)))]
    public array $buddies;

    #[OA\Property(type: 'array', items: new OA\Items(ref: new Model(type: Profile::class)))]
    public array $myRequests;

    #[OA\Property(type: 'array', items: new OA\Items(ref: new Model(type: Profile::class)))]
    public array $requestsToMe;

    public function __construct()
    {
        $this->buddies = [];
        $this->myRequests = [];
        $this->requestsToMe = [];
    }
}
