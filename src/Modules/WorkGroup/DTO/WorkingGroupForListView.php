<?php

declare(strict_types=1);

namespace Foodsharing\Modules\WorkGroup\DTO;

use DateTime;
use Foodsharing\Modules\Foodsaver\Profile;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;

class WorkingGroupForListView
{
    #[OA\Property(example: 332)]
    public int $id;

    #[OA\Property(example: 'Betriebsketten')]
    public string $name;

    #[OA\Property(example: 'Hier gehts um die Zusammenarbeit mit Betriebsketten.')]
    public string $description;

    #[OA\Property(description: 'The ID of the working group\'s category', example: 3)]
    public ?int $categoryId;

    #[OA\Property(description: 'The number of members in the working group', example: 5110)]
    public int $memberCount;

    #[OA\Property(description: 'Whether the current user has access to the working group')]
    public bool $mayAccess;

    #[OA\Property(description: 'Whether the current user is a member of the working group. Access is wider than membership: orga members may access every group.')]
    public bool $isMember;

    #[OA\Property(description: 'Whether the current user can apply to the working group')]
    public bool $mayApply;

    #[OA\Property(description: 'The application prompt to show to the user when they want to apply for the working group. Can be null, so that the default application prompt is used.')]
    public ?string $applicationPrompt;

    #[OA\Property(description: 'Whether the current user can join the working group directly without approval')]
    public bool $mayJoin;

    #[OA\Property(description: 'Whether the current user has already applied to the working group')]
    public bool $hasAppliedFor;

    #[OA\Property(description: 'When the latest activity in the group happened. Currently this only considers activity in the group\'s forum.')]
    public ?DateTime $latestActivity;

    #[OA\Property(description: 'Whether the members or admins of the working group have special permissions')]
    public bool $hasSpecialPermissions;

    #[OA\Property(description: 'The type of the working group\'s function if it has one. Null if the working group does not have a function.', example: null)]
    public ?int $groupFunctionType;

    #[OA\Property(description: 'The email address of the working group, without the @foodsharing.network ending', example: 'ketten')]
    public string $email;

    #[OA\Property(description: 'The URL of the working group\'s image', example: '99d1fb45-9748-3510-ac02-87b46c40048c')]
    public ?string $image;

    /**
     * @var Profile[] $admins
     */
    #[OA\Property(description: 'The admins of the working group', type: 'array',
        items: new OA\Items(ref: new Model(type: Profile::class))
    )]
    public array $admins;

    /**
     * @var SubGroupEntry[] $subGroups
     */
    #[OA\Property(description: 'The subgroups of the working group', type: 'array',
        items: new OA\Items(ref: new Model(type: SubGroupEntry::class))
    )]
    public array $subGroups;
}

class SubGroupEntry
{
    #[OA\Property(example: 2280)]
    public int $id;

    #[OA\Property(example: 'Rewe')]
    public string $name;

    #[OA\Property(description: 'The email address of the working group, without the @foodsharing.network ending', example: 'rewe')]
    public string $email;

    #[OA\Property(description: 'When the latest activity in the group happened. Currently this only considers activity in the group\'s forum.')]
    public ?DateTime $latestActivity;
}
