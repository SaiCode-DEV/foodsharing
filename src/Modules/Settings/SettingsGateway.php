<?php

namespace Foodsharing\Modules\Settings;

use DateTime;
use Exception;
use Foodsharing\Modules\Core\BaseGateway;
use Foodsharing\Modules\Core\DBConstants\Foodsaver\UserOptionType;
use Foodsharing\RestApi\Models\Notifications\NewsletterChat;
use Foodsharing\RestApi\Models\Settings\SleepStatusRequest;

class SettingsGateway extends BaseGateway
{
    public function logChangedSetting(int $fsId, array $old, array $new, array $logChangedKeys, int $changerId = null): void
    {
        if (!$changerId) {
            $changerId = $fsId;
        }
        /* the logic is not exactly matching the update mechanism but should be close enough to get all changes... */
        foreach ($logChangedKeys as $k) {
            if (array_key_exists($k, $new) && $new[$k] != $old[$k]) {
                $this->db->insert(
                    'fs_foodsaver_change_history',
                    [
                        'date' => date(DateTime::ISO8601),
                        'fs_id' => $fsId,
                        'changer_id' => $changerId,
                        'object_name' => $k,
                        'old_value' => $old[$k],
                        'new_value' => $new[$k]
                    ]
                );
            }
        }
    }

    public function saveInfoSettings(int $fsId, NewsletterChat $newsletterChat): int
    {
        return $this->db->update(
            'fs_foodsaver',
            [
                'newsletter' => $newsletterChat->newsletter,
                'infomail_message' => $newsletterChat->chat
            ],
            ['id' => $fsId]
        );
    }

    /**
     * Deactivates the newsletter for the user with the given email address. This is also requires the user's token. The
     * function does nothing, if the email address does not exist or if the token is wrong.
     */
    public function unsubscribeNewsletter(string $email, string $token): void
    {
        $this->db->update('fs_foodsaver', ['newsletter' => 0], ['email' => $email, 'token' => $token]);
    }

    public function getSleepData(int $fsId): array
    {
        return $this->db->fetchByCriteria(
            'fs_foodsaver',
            [
                'sleep_status',
                'sleep_from',
                'sleep_until',
                'sleep_msg'
            ],
            ['id' => $fsId]
        );
    }

    public function updateSleepMode(int $fsId, SleepStatusRequest $request): int
    {
        $from = $request->from ?: null;
        $to = $request->to ?: null;

        return $this->db->update(
            'fs_foodsaver',
            [
                'sleep_status' => $request->mode,
                'sleep_from' => $from ? $from->format('Y-m-d H:i:s') : null,
                'sleep_until' => $to ? $to->format('Y-m-d H:i:s') : null,
                'sleep_msg' => $request->message ? strip_tags($request->message) : null
            ],
            ['id' => $fsId]
        );
    }

    public function addNewMail(int $fsId, string $email, string $token): int
    {
        return $this->db->insertOrUpdate(
            'fs_mailchange',
            [
                'foodsaver_id' => $fsId,
                'newmail' => strip_tags($email),
                'time' => $this->db->now(),
                'token' => strip_tags($token)
            ]
        );
    }

    public function changeMail(int $fsId, string $email): int
    {
        $this->deleteMailChanges($fsId);

        return $this->db->update(
            'fs_foodsaver',
            ['email' => strip_tags($email)],
            ['id' => $fsId]
        );
    }

    public function abortChangemail(int $fsId): int
    {
        return $this->deleteMailChanges($fsId);
    }

    private function deleteMailChanges(int $fsId): int
    {
        return $this->db->delete(
            'fs_mailchange',
            ['foodsaver_id' => $fsId]
        );
    }

    public function getMailChange(int $fsId): string
    {
        return $this->db->fetchValueByCriteria(
            'fs_mailchange',
            'newmail',
            ['foodsaver_id' => $fsId]
        );
    }

    public function getNewMail(int $fsId, string $token): ?string
    {
        return $this->db->fetchValueByCriteria(
            'fs_mailchange',
            'newmail',
            [
                'token' => strip_tags($token),
                'foodsaver_id' => $fsId
            ]
        );
    }

    public function saveApiToken(int $fsId, string $token): void
    {
        $this->db->insertOrUpdate(
            'fs_apitoken',
            [
                'foodsaver_id' => $fsId,
                'token' => $token
            ]
        );
    }

    /**
     * Returns an option for the user, or null if the option is not set for the user.
     * See {@see UserOptionType},.
     */
    public function getUserOption(int $userId, UserOptionType $optionType): ?string
    {
        try {
            return $this->db->fetchValueByCriteria('fs_foodsaver_has_options', 'option_value', [
                'foodsaver_id' => $userId,
                'option_type' => $optionType->value
            ]);
        } catch (Exception) {
            return null;
        }
    }

    /**
     * Returns an array of options for the users, or null if the option is not set for a user.
     *
     * @return array<array<string, mixed>>
     */
    public function getUsersOption(array $userIds, UserOptionType $optionType): array
    {
        try {
            $results = $this->db->fetchAllByCriteria('fs_foodsaver_has_options', ['foodsaver_id', 'option_value'], [
                'foodsaver_id' => $userIds,
                'option_type' => $optionType->value
            ]);
        } catch (Exception) {
            $results = [];
        }

        $optionMap = [];
        $userOptions = [];
        foreach ($results as $result) {
            $optionMap[$result['foodsaver_id']] = $result['option_value'];
        }
        foreach ($userIds as $userId) {
            $userOptions[] = [
                'userId' => $userId,
                'option' => $optionMap[$userId] ?? null
            ];
        }

        return $userOptions;
    }

    /**
     * Sets an option for the user. If the option is already existing for this user, it will be
     * overwritten. See {@see UserOptionType},.
     */
    public function setUserOption(int $userId, UserOptionType $optionType, string $value): void
    {
        $this->db->insertOrUpdate('fs_foodsaver_has_options', [
            'foodsaver_id' => $userId,
            'option_type' => $optionType->value,
            'option_value' => $value,
        ]);
    }

    /**
     * Returns the user's token for the iCal API, or null if the user does not have a token.
     */
    public function getApiToken(int $userId): ?string
    {
        try {
            return $this->db->fetchValueByCriteria('fs_apitoken', 'token', ['foodsaver_id' => $userId]);
        } catch (Exception) {
            return null;
        }
    }

    /**
     * Deletes the user's token for the iCal API.
     */
    public function removeApiToken(int $userId): void
    {
        $this->db->delete('fs_apitoken', ['foodsaver_id' => $userId]);
    }

    /**
     * Returns the user to whom the token belongs, or null if the token does not exist.
     */
    public function getUserForToken(string $token): ?int
    {
        try {
            return $this->db->fetchValueByCriteria('fs_apitoken', 'foodsaver_id', ['token' => $token]);
        } catch (Exception) {
            return null;
        }
    }
}
