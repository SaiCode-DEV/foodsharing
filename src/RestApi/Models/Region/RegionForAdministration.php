<?php

namespace Foodsharing\RestApi\Models\Region;

use JMS\Serializer\Annotation\Type;
use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

// TODO add validations (also for exact values in type and wgfunc)
class RegionForAdministration
{
    #[OA\Property(example: 1, description: 'Region identifier')]
    public ?int $id = null;

    #[OA\Property(example: 'Göttingen', description: 'Name of region.')]
    #[Assert\NotBlank]
    public string $name = '';

    #[OA\Property(example: 7, description: 'Type of the region')]
    public int $type = 0;

    #[OA\Property(type: 'array', description: 'User identifiers of the regions admins / ambassadors',
        items: new OA\Items(type: 'int', example: 1))]
    #[Type('array<int>')]
    public array $adminIds = [];

    #[OA\Property(example: 1, description: 'Region identifier of the parent region')]
    public int $parentId = 0;

    #[OA\Property(example: 1, description: 'Region identifier of the master region')]
    public int $masterId = 0;

    #[OA\Property(example: 'goettingen', description: 'Mailbox name without @foodsharing.network ending')]
    #[Assert\NotBlank]
    #[Assert\Regex('/^[\w.\-_]+$/')]
    public string $mailbox = '';

    #[OA\Property(example: 'foodsharing Göttingen', description: 'Email sender name')]
    public string $emailName = '';

    #[OA\Property(example: 4, description: 'Identifier of the GOALS working group function')]
    public int $workgroupFunction = 0;

    #[OA\Property(example: false, description: 'Whether moderators of the region are allowed to delete forum posts')]
    public bool $allowHidingInForum = false;

    #[OA\Property(example: false, description: 'Whether the region can be edited by the current user')]
    #[Type('bool')]
    public bool $canEdit = false;

    public static function createFromArray(array $data)
    {
        $region = new RegionForAdministration();
        $region->id = $data['id'];
        $region->name = $data['name'];
        $region->type = $data['type'];
        $region->parentId = $data['parent_id'];
        $region->masterId = $data['master'];
        $region->emailName = $data['email_name'];
        $region->adminIds = $data['adminIds'];
        $region->canEdit = $data['canEdit'] ?? false;

        return $region;
    }
}
