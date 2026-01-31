<?php

namespace Foodsharing\Modules\Core;

use Foodsharing\Modules\Blog\DTO\BlogPost;
use Foodsharing\Modules\Region\DTO\ForumThreadForListView;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;

class PaginatedContent
{
    #[OA\Property(example: 42, description: 'The total number of entries that are available, not only the count of entities included.')]
    public int $totalCount;

    #[OA\Property(example: 20, description: 'The 0-based index of the first entry that is included.')]
    public int $offset;

    public array $entries;

    public static function create(int $totalCount, int $offset, array $entries): self
    {
        $paginatedContent = new self();
        $paginatedContent->totalCount = $totalCount;
        $paginatedContent->offset = $offset;
        $paginatedContent->entries = $entries;

        return $paginatedContent;
    }
}

/**
 * The following classes are type-save variants of PaginatedContent.
 * These should never really be used except as an API return type.
 */
class PaginatedForumThreadsForListView extends PaginatedContent
{
    #[OA\Property(property: 'entries', type: 'array', items: new OA\Items(ref: new Model(type: ForumThreadForListView::class)))]
    public array $entries;
}

class PaginatedBlogPosts extends PaginatedContent
{
    #[OA\Property(property: 'entries', type: 'array', items: new OA\Items(ref: new Model(type: BlogPost::class)))]
    public array $entries;
}
