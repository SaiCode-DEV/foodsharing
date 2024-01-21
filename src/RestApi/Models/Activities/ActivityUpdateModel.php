<?php

declare(strict_types=1);

namespace Foodsharing\RestApi\Models\Activities;

use Foodsharing\Modules\Activity\DTO\ActivityUpdate;
use Foodsharing\Modules\Activity\DTO\ActivityUpdateMailbox;
use Nelmio\ApiDocBundle\Annotation\Model;
use OpenApi\Attributes as OA;

#[OA\Schema(description: 'A list of all news from the activated activities of the logged-in user')]
class ActivityUpdateModel
{
    #[OA\Property(
        title: 'A list of all updates distinguishes between mailbox and all other',
        type: 'array',
        items: new OA\Items(
            oneOf: [
                new OA\Property(
                    ref: new Model(type: ActivityUpdate::class),
                    title: 'All updates, except mailbox'
                ),
                new OA\Property(
                    ref: new Model(type: ActivityUpdateMailbox::class),
                    title: 'Contains all mailbox updates'
                ),
            ]
        )
    )]
    public readonly array $updates;

    public function __construct(array $updates)
    {
        $this->updates = $updates;
    }
}
