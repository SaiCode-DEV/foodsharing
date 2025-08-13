<?php

namespace Foodsharing\RestApi;

use DateTime;
use Foodsharing\Modules\Core\DTO\Address;

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

    /**
     * Returns the response data for a store.
     *
     * @param array $data the store data from the database
     */
    public static function normalizeStore(array $data, bool $includeDetails): array
    {
        $store = [
            'id' => (int)$data['id'],
            'name' => $data['name'],
            'group' => [
                'id' => $data['bezirk_id'],
                'name' => $data['bezirk'],
            ],
            'lat' => (float)$data['lat'],
            'lon' => (float)$data['lon'],
            'storeCategoryId' => (int)$data['betrieb_kategorie_id'],
            'cooperationStatus' => (int)$data['betrieb_status_id'],
            'teamStatus' => (int)$data['team_status'],
            'chain' => [],
            'responsibleUserIds' => [],
        ];

        if (isset($data['kette'])) {
            $store['chain'] = $data['kette'];
        }
        if (isset($data['verantwortlicher']) && is_array($data['verantwortlicher'])) {
            $store['responsibleUserIds'] = array_map(fn ($u) => (int)$u['id'], $data['verantwortlicher']);
        }

        if ($includeDetails) {
            $store = array_merge($store, [
                'address' => Address::createFromArray([
                    'street' => $data['str'],
                    'city' => $data['stadt'],
                    'postalCode' => $data['plz']
                ]),
                'phone' => $data['telefon'],
                'fax' => $data['fax'],
                'email' => $data['email'],
                'contactPerson' => $data['ansprechpartner'],
                'updatedAt' => DateTime::createFromFormat('Y-m-d', (string)$data['status_date'])->setTime(0, 0),
                'notes' => [],
            ]);
        }

        return $store;
    }
}
