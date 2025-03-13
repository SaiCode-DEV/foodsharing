<?php

namespace Foodsharing\RestApi;

use Carbon\Carbon;
use DateTime;
use Foodsharing\Modules\Core\DBConstants\Foodsaver\SleepStatus;
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
            'sleepStatus' => self::isSleeping($data),
            'mobile' => $data['handy'] ?? '',
            'landline' => $data['telefon'] ?? '',
            // 'isVerified' => boolval($data['verified']),
            // 'roleLevel' => $data['quiz_rolle'], // should be added to FS:getFoodsaverDetails
            /* team-related data: */
            'isManager' => boolval($data['verantwortlich'] ?? false),
            // 'team_active' (membership status) should be included as well
        ];
    }

    private static function isSleeping(array $data, string $prefix = ''): bool
    {
        $sleepFrom = null;
        $sleepUntil = null;
        if (isset($data[$prefix . 'sleep_status'])) {
            $sleepState = $data[$prefix . 'sleep_status'];
            $sleepFrom = $data[$prefix . 'sleep_from'] ?? null;
            $sleepUntil = $data[$prefix . 'sleep_until'] ?? null;
        } elseif (isset($data['sleep_status'])) {
            $sleepState = $data['sleep_status'];
        } else {
            $sleepState = SleepStatus::NONE;
        }

        return match ($sleepState) {
            SleepStatus::TEMP => $sleepFrom && Carbon::now()->isSameDay(Carbon::parse($sleepFrom))
                || $sleepFrom && Carbon::now()->isAfter(Carbon::parse($sleepFrom)->startOfDay())
                || ($sleepFrom && $sleepUntil && Carbon::now()->isBefore(Carbon::parse($sleepUntil)->addDay()) && Carbon::now()->isAfter(Carbon::parse($sleepFrom)->endOfDay())),
            SleepStatus::FULL => true,
            default => false,
        };
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
