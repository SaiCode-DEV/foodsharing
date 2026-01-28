<?php

declare(strict_types=1);

namespace Foodsharing\Modules\Search\DTO;

use Foodsharing\Modules\Foodsaver\Profile;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;

class WorkingGroupSearchResult extends SearchResult
{
    #[OA\Property(example: 'bildung.muenster@foodsharing.network', description: "Includes the '@...' mail ending.")]
    public string $email;

    #[OA\Property(example: 1)]
    public int $parentId;

    #[OA\Property(example: 'Münster')]
    public string $parentName;

    #[OA\Property(description: 'Whether the searching user is member in the working group.')]
    public bool $isMember;

    #[OA\Property(description: 'Whether the searching user is admin in the working group.')]
    public bool $isAdmin;

    #[OA\Property(type: 'array', items: new OA\Items(ref: new Model(type: Profile::class)))]
    public array $admins;

    public static function createFromArray(array $data): WorkingGroupSearchResult
    {
        $result = new WorkingGroupSearchResult();
        $result->id = $data['id'];
        $result->name = $data['name'];
        $result->email = $data['email'] ?? '';
        if (!empty($data['email']) && !str_contains((string)$data['email'], '@')) {
            $result->email .= '@' . PLATFORM_MAILBOX_HOST;
        }
        $result->parentId = $data['parent_id'];
        $result->parentName = $data['parent_name'];
        $result->isMember = boolval($data['is_member']);
        $result->isAdmin = boolval($data['is_admin']);
        $result->admins = self::formatUserList($data, 'admin');
        $result->setSearchString($data);

        return $result;
    }
}
