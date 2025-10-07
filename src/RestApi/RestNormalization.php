<?php

namespace Foodsharing\RestApi;

/**
 * Utility class that can be user by all controllers to format objects for
 * uniform Rest responses.
 */
class RestNormalization
{
    /**
     * Returns the response data for a foodsaver in store context: the above, plus
     * phone numbers, verification state, passed quiz level and if they're manager.
     *
     * @param array $data the user data from the database
     */
    public static function normalizeStoreUser(array $data): array
    {
        if (!isset($data['id'])) {
            // the user can no longer be found
            $data['id'] = -1;
            $data['name'] = '?';
        }

        return [
            /* user-related data: */
            'id' => (int)$data['id'],
            'name' => $data['name'],
            'avatar' => $data['photo'] ?? null,
            'isSleeping' => $data['is_sleeping'] ?? false,
            'mobile' => $data['handy'] ?? '',
            'landline' => $data['telefon'] ?? '',
            // 'isVerified' => boolval($data['verified']),
            // 'roleLevel' => $data['quiz_rolle'], // should be added to FS:getFoodsaverDetails
            /* team-related data: */
            'isManager' => boolval($data['verantwortlich'] ?? false),
            // 'team_active' (membership status) should be included as well
        ];
    }
}
