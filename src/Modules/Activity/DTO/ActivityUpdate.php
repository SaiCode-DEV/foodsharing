<?php

declare(strict_types=1);

namespace Foodsharing\Modules\Activity\DTO;

use Carbon\Carbon;
use DateTime;
use JMS\Serializer\Annotation\SerializedName;
use JMS\Serializer\Annotation\Type;
use OpenApi\Attributes as OA;

#[OA\Schema()]
class ActivityUpdate
{
    #[OA\Property(title: 'Type of the Update', type: 'string')]
    #[Type('string')]
    public string $type;

    #[OA\Property(title: 'The Time of the Update', type: 'string')]
    #[Type('DateTime')]
    public DateTime $time;

    #[OA\Property(title: 'The Name of the Update', type: 'string')]
    #[Type('string')]
    public string $title;

    #[OA\Property(title: 'a more detailed description', type: 'string')]
    #[Type('string')]
    public string $desc;

    #[OA\Property(title: 'The Source of the Update', type: 'string', nullable: true)]
    #[Type('string')]
    public ?string $source;

    #[OA\Property(property: 'source_suffix', title: 'Suffix of the source of the update', type: 'string', nullable: true)]
    #[Type('string')]
    #[SerializedName('source_suffix')]
    public ?string $sourceSuffix;

    #[OA\Property(title: 'Icon path for the update', type: 'string', nullable: true)]
    #[Type('string')]
    public ?string $icon;

    #[OA\Property(title: 'The Gallery', type: 'array', items: new OA\Items(), nullable: true)]
    #[Type('array')]
    public ?array $gallery;

    #[OA\Property(property: 'fs_id', title: 'Id from Creator', type: 'integer')]
    #[Type('int')]
    #[SerializedName('fs_id')]
    public int $fsId;

    #[OA\Property(property: 'fs_name', title: 'The Name from Creator', type: 'string', nullable: true)]
    #[Type('string')]
    #[SerializedName('fs_name')]
    public ?string $fsName;

    #[OA\Property(property: 'entity_id', title: 'id from Update', type: 'integer')]
    #[Type('int')]
    #[SerializedName('entity_id')]
    public int $entityId;

    #[OA\Property(property: 'region_id', title: 'id of the associated region', type: 'integer', nullable: true)]
    #[Type('int')]
    #[SerializedName('region_id')]
    public ?int $regionId;

    #[OA\Property(property: 'forum_post', title: 'the id of the corresponding forum post', type: 'integer', nullable: true)]
    #[Type('int')]
    #[SerializedName('forum_post')]
    public ?int $forumPost;

    #[OA\Property(property: 'forum_type', title: 'Type of forum', type: 'string', nullable: true)]
    #[Type('string')]
    #[SerializedName('forum_type')]
    public ?string $forumType;

    public static function create(
        string $type,
        string $time,
        string $title,
        string $desc,
        ?string $source,
        ?string $sourceSuffix,
        ?string $icon,
        ?array $gallery,
        int $fsId,
        ?string $fsName,
        int $entityId,
        ?int $regionId = null,
        ?int $forumPost = null,
        ?string $forumType = null,
    ): self {
        $item = new self();

        $item->type = $type;
        $item->time = Carbon::createFromTimestamp($time);
        $item->title = $title;
        $item->desc = $desc;
        $item->source = $source;
        $item->sourceSuffix = $sourceSuffix;
        $item->icon = $icon;
        $item->gallery = $gallery;
        $item->fsId = $fsId;
        $item->fsName = $fsName;
        $item->entityId = $entityId;
        $item->regionId = $regionId;
        $item->forumPost = $forumPost;
        $item->forumType = $forumType;

        return $item;
    }
}
