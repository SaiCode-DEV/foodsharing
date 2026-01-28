<?php

declare(strict_types=1);

namespace Foodsharing\Modules\Search\DTO;

use Foodsharing\Modules\Foodsaver\Profile;
use Nelmio\ApiDocBundle\Annotation\Model;
use OpenApi\Attributes as OA;

class RegionSearchResult extends SearchResult
{
    #[OA\Property(example: 'muenster@foodsharing.network', description: "Email address of the region. Includes the '@...' mail ending.")]
    public string $email;

    #[OA\Property(example: 42, description: 'ID of the region.')]
    public int $parentId;

    #[OA\Property(example: 'Nordrhein-Westfalen', description: 'Name of the regions parent region.')]
    public string $parentName;

    #[OA\Property(description: 'Whether the searching user is member in the region.')]
    public bool $isMember;

    #[OA\Property(type: 'array', items: new OA\Items(ref: new Model(type: Profile::class)))]
    public array $ambassadors;

    public static function createFromArray(array $data): RegionSearchResult
    {
        $result = new RegionSearchResult();
        $result->id = $data['id'];
        $result->name = $data['name'];
        $result->email = $data['email'] ?? '';
        if (!empty($data['email']) && !str_contains((string)$data['email'], '@')) {
            $result->email .= '@' . PLATFORM_MAILBOX_HOST;
        }
        $result->parentId = $data['parent_id'];
        $result->parentName = $data['parent_name'];
        $result->isMember = boolval($data['is_member']);
        $result->ambassadors = self::formatUserList($data, 'ambassador');
        $result->setSearchString($data);

        return $result;
    }
}
