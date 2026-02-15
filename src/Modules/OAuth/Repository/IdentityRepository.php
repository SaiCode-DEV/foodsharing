<?php

declare(strict_types=1);

namespace Foodsharing\Modules\OAuth\Repository;

use Foodsharing\Modules\Core\Database;
use Foodsharing\Modules\OAuth\Entity\UserEntity;
use OpenIDConnectServer\Entities\ClaimSetInterface;
use OpenIDConnectServer\Repositories\IdentityProviderInterface;

class IdentityRepository implements IdentityProviderInterface
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    /**
     * Get user entity by identifier.
     *
     * @param mixed $identifier User identifier (user ID)
     * @return ClaimSetInterface|null
     */
    public function getUserEntityByIdentifier($identifier)
    {
        $user = $this->db->fetchByCriteria('fs_foodsaver', [
            'id', 'name', 'nachname', 'email', 'photo'
        ], ['id' => $identifier]);

        if (!$user) {
            return null;
        }

        $userEntity = new UserEntity();
        $userEntity->setIdentifier((string)$user['id']);

        // Set claims that will be available based on scopes
        $claims = [
            'sub' => (string)$user['id'],
            'name' => trim(($user['name'] ?? '') . ' ' . ($user['nachname'] ?? '')),
            'given_name' => $user['name'] ?? '',
            'family_name' => $user['nachname'] ?? '',
            'email' => $user['email'] ?? '',
            'email_verified' => true,
        ];

        if (!empty($user['photo'])) {
            $claims['picture'] = BASE_URL . '/images/profile/' . $user['photo'];
        }

        // Add region information (will be filtered by ClaimExtractor based on scopes)
        $regions = $this->db->fetchAll(
            'SELECT DISTINCT b.id FROM fs_foodsaver_has_bezirk fb 
             JOIN fs_bezirk b ON fb.bezirk_id = b.id 
             WHERE fb.foodsaver_id = :userId AND fb.active = 1',
            [':userId' => $identifier]
        );
        $claims['region_ids'] = array_map(fn ($r) => (string)$r['id'], $regions);

        // Add regions where user is ambassador
        $ambassadorRegions = $this->db->fetchAll(
            'SELECT DISTINCT bezirk_id FROM fs_botschafter 
             WHERE foodsaver_id = :userId',
            [':userId' => $identifier]
        );
        $claims['region_ambassador'] = array_map(fn ($r) => (string)$r['bezirk_id'], $ambassadorRegions);

        $userEntity->setClaims($claims);

        return $userEntity;
    }
}
