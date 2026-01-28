<?php

declare(strict_types=1);

namespace Foodsharing\Modules\Search\DTO;

use Nelmio\ApiDocBundle\Annotation\Model;
use OpenApi\Attributes as OA;

class MixedSearchResult
{
    /** @var RegionSearchResult[] */
    #[OA\Property(type: 'array', items: new OA\Items(ref: new Model(type: RegionSearchResult::class)))]
    public array $regions;

    /** @var WorkingGroupSearchResult[] */
    #[OA\Property(type: 'array', items: new OA\Items(ref: new Model(type: WorkingGroupSearchResult::class)))]
    public array $workingGroups;

    /** @var StoreSearchResult[] */
    #[OA\Property(type: 'array', items: new OA\Items(ref: new Model(type: StoreSearchResult::class)))]
    public array $stores;

    /** @var FoodSharePointSearchResult[] */
    #[OA\Property(type: 'array', items: new OA\Items(ref: new Model(type: FoodSharePointSearchResult::class)))]
    public array $foodSharePoints;

    /** @var ChatSearchResult[] */
    #[OA\Property(type: 'array', items: new OA\Items(ref: new Model(type: ChatSearchResult::class)))]
    public array $chats;

    /** @var ThreadSearchResult[] */
    #[OA\Property(type: 'array', items: new OA\Items(ref: new Model(type: ThreadSearchResult::class)))]
    public array $threads;

    /** @var UserSearchResult[] */
    #[OA\Property(type: 'array', items: new OA\Items(ref: new Model(type: UserSearchResult::class)))]
    public array $users;

    /** @var MailSearchResult[] */
    #[OA\Property(type: 'array', items: new OA\Items(ref: new Model(type: MailSearchResult::class)))]
    public array $mails;

    /** @var EventSearchResult[] */
    #[OA\Property(type: 'array', items: new OA\Items(ref: new Model(type: EventSearchResult::class)))]
    public array $events;

    /** @var PollSearchResult[] */
    #[OA\Property(type: 'array', items: new OA\Items(ref: new Model(type: PollSearchResult::class)))]
    public array $polls;

    /** @var array<string, float> timings, only used for testing in beta */
    public array $timings;
}
