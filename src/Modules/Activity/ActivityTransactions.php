<?php

namespace Foodsharing\Modules\Activity;

use Carbon\Carbon;
use DateTime;
use Foodsharing\Lib\Session;
use Foodsharing\Modules\Activity\DTO\ActivityFilter;
use Foodsharing\Modules\Activity\DTO\ActivityFilterCategory;
use Foodsharing\Modules\Activity\DTO\ActivityUpdate;
use Foodsharing\Modules\Activity\DTO\ActivityUpdateMailbox as MailboxUpdate;
use Foodsharing\Modules\Activity\DTO\ImageActivityFilter;
use Foodsharing\Modules\Buddy\BuddyTransactions;
use Foodsharing\Modules\Core\DBConstants\Foodsaver\Role;
use Foodsharing\Modules\Core\DBConstants\Foodsaver\UserOptionType;
use Foodsharing\Modules\Core\DBConstants\Unit\UnitType;
use Foodsharing\Modules\Mailbox\MailboxGateway;
use Foodsharing\Modules\Settings\SettingsTransactions;
use Foodsharing\Modules\Unit\CurrentUserUnitsInterface;
use Foodsharing\RestApi\Models\Activities\ActivityFilterItem;
use Foodsharing\Utility\ImageHelper;
use Symfony\Contracts\Translation\TranslatorInterface;

class ActivityTransactions
{
    public function __construct(
        private readonly ActivityGateway $activityGateway,
        private readonly MailboxGateway $mailboxGateway,
        private readonly ImageHelper $imageHelper,
        private readonly TranslatorInterface $translator,
        private readonly Session $session,
        private readonly SettingsTransactions $settingsTransaction,
        private readonly BuddyTransactions $buddyTransactions,
        private readonly CurrentUserUnitsInterface $currentUserUnits
    ) {
    }

    /**
     * Returns all activity filters for the logged in user sorted into categories.
     *
     * @return array ActivityFilterCategory[]
     */
    public function getFilters(): array
    {
        // list of currently excluded activities
        $excluded = json_decode($this->settingsTransaction->getOption(UserOptionType::ACTIVITY_LISTINGS), true) ?: [];

        // regions and groups
        $regionOptions = [];
        $groupOptions = [];
        if ($bezirke = $this->currentUserUnits->getRegions()) {
            foreach ($bezirke as $b) {
                $option = ActivityFilter::create($b['id'], $b['name'],
                    !isset($excluded['bezirk-' . $b['id']])
                );
                if (UnitType::isGroup($b['type'])) {
                    $groupOptions[] = $option;
                } else {
                    $regionOptions[] = $option;
                }
            }
        }

        // mailboxes
        $mailboxOptions = [];
        if ($boxes = $this->mailboxGateway->getBoxes(
            $this->currentUserUnits->isAmbassador(),
            $this->session->id(),
            $this->session->mayRole(Role::STORE_MANAGER))
        ) {
            $mailboxOptions = array_map(fn ($b) => ActivityFilter::create(
                $b['id'], $b['name'] . '@' . PLATFORM_MAILBOX_HOST,
                !isset($excluded['mailbox-' . $b['id']])
            ), $boxes);
        }

        // buddy walls
        $buddyOptions = [];
        if ($buddyIds = $this->buddyTransactions->listBuddiesIds()) {
            $buddies = $this->activityGateway->fetchAllBuddies($buddyIds);
            $buddyOptions = array_map(fn ($b) => ImageActivityFilter::create(
                $b['name'], $b['id'], !isset($excluded['buddywall-' . $b['id']]),
                $this->imageHelper->img($b['photo'])
            ), $buddies);
        }

        return [
            ActivityFilterCategory::create('bezirk', $this->translator->trans('globals.type.my_groups'),
                $this->translator->trans('globals.type.workingGroups'), $groupOptions),
            ActivityFilterCategory::create('bezirk', $this->translator->trans('globals.type.my_regions'),
                $this->translator->trans('globals.type.regions'), $regionOptions),
            ActivityFilterCategory::create('mailbox', $this->translator->trans('globals.type.my_mailboxes'),
                $this->translator->trans('globals.type.mailboxes'), $mailboxOptions),
            ActivityFilterCategory::create('buddywall', $this->translator->trans('globals.type.my_buddies'),
                $this->translator->trans('globals.type.buddies'), $buddyOptions),
        ];
    }

    /**
     * Sets the deactivated activities for the logged in user.
     *
     * @param array<ActivityFilterItem> $excluded a list of activities to be deactivated. List entries should be objects with
     * 'index' and 'id' entries
     */
    public function setExcludedFilters(array $excluded): void
    {
        $list = [];
        foreach ($excluded as $item) {
            if ($item->id > 0) {
                $list[$item->index . '-' . $item->id] = [
                    'index' => $item->index,
                    'id' => $item->id,
                ];
            }
        }

        $this->settingsTransaction->setOption(UserOptionType::ACTIVITY_LISTINGS, json_encode($list));
    }

    /**
     * Returns a paginated list of dashboard update objects, filtered by excluding all undesired update sources.
     *
     * @param int $page Which page / chunk of updates to return
     */
    public function getUpdateData(int $page): array
    {
        $hidden_ids = [
            'bezirk' => [],
            'mailbox' => [],
            'buddywall' => [],
        ];

        // Store which update sources to skip, keyed by update type and entity ID
        $sesOptions = $this->settingsTransaction->getOption(UserOptionType::ACTIVITY_LISTINGS);
        if ($sesOptions) {
            $activities = json_decode($sesOptions, true);
            if ($activities) {
                foreach ($activities as $o) {
                    if (isset($hidden_ids[$o['index']])) {
                        $hidden_ids[$o['index']][$o['id']] = $o['id'];
                    }
                }
            }
        }

        return array_merge(
            $this->loadForumUpdates($page, $hidden_ids['bezirk']),
            $this->loadStoreUpdates($page),
            $this->loadMailboxUpdates($page, $hidden_ids['mailbox']),
            $this->loadFoodSharePointWallUpdates($page),
            $this->loadFriendWallUpdates($page, $hidden_ids['buddywall']),
            $this->loadEventWallUpdates($page)
        );
    }

    /**
     * @return array<ActivityUpdate>
     */
    private function loadEventWallUpdates(int $page): array
    {
        $updates = $this->activityGateway->fetchAllEventUpdates($this->session->id(), $page);

        $activities = [];
        foreach ($updates as $update) {
            $activities[] = ActivityUpdate::create(
                'event',
                $this->getCleanedTimestamp($update['time_ts']),
                $update['name'],
                $update['body'] ?? '',
                $update['event_region'],
                '',
                $update['fs_photo'] ?? '',
                $update['gallery'] ?? [],
                $update['fs_id'],
                $update['fs_name'],
                $update['event_id'],
            );
        }

        return $activities;
    }

    /**
     * @return array<ActivityUpdate>
     */
    private function loadFoodSharePointWallUpdates(int $page): array
    {
        $updates = $this->activityGateway->fetchAllFoodSharePointWallUpdates($this->session->id(), $page);

        $activities = [];

        foreach ($updates as $update) {
            $activities[] = ActivityUpdate::create(
                'foodsharepoint',
                $this->getCleanedTimestamp($update['time_ts']),
                $update['name'],
                $update['body'] ?? '',
                $update['fsp_location'],
                '',
                $update['fs_photo'] ?? '',
                $update['gallery'] ?? [],
                $update['fs_id'],
                $update['fs_name'],
                $update['fsp_id'],
                $update['region_id'],
            );
        }

        return $activities;
    }

    /**
     * @return array<ActivityUpdate>
     */
    private function loadFriendWallUpdates(int $page, array $hidden_ids): array
    {
        $buddy_ids = [];

        if ($b = $this->buddyTransactions->listBuddiesIds()) {
            $buddy_ids = $b;
        }

        $buddy_ids[$this->session->id()] = $this->session->id();

        $bids = [];
        foreach ($buddy_ids as $id) {
            if (!in_array($id, $hidden_ids)) {
                $bids[] = $id;
            }
        }

        $updates = $this->activityGateway->fetchAllFriendWallUpdates($bids, $page);
        if (empty($updates)) {
            return [];
        }

        $activities = [];
        foreach ($updates as $update) {
            $isOwn = $update['fs_id'] === $this->session->id();

            $activities[] = ActivityUpdate::create(
                'friendWall',
                $this->getCleanedTimestamp($update['time_ts']),
                '',
                $update['body'] ?? '',
                $update['fs_name'],
                $isOwn ? '_own' : '',
                $update['fs_photo'] ?? '',
                $update['gallery'] ?? [],
                $update['fs_id'],
                $update['fs_name'],
                $update['fs_id'],
            );
        }

        return $activities;
    }

    private function loadMailboxUpdates(int $page, array $hidden_ids): array
    {
        $boxes = $this->mailboxGateway->getBoxes(
            $this->currentUserUnits->isAmbassador(),
            $this->session->id(),
            $this->session->mayRole(Role::STORE_MANAGER)
        );

        if (empty($boxes)) {
            return [];
        }

        $mb_ids = [];
        foreach ($boxes as $b) {
            if (!isset($hidden_ids[$b['id']])) {
                $mb_ids[] = $b['id'];
            }
        }

        if (count($mb_ids) === 0) {
            return [];
        }

        $updates = $this->activityGateway->fetchAllMailboxUpdates($mb_ids, $page);

        if (empty($updates)) {
            return [];
        }

        $out = [];
        foreach ($updates as $u) {
            $sender = json_decode((string)$u['sender'], true, 512, JSON_THROW_ON_ERROR + JSON_INVALID_UTF8_IGNORE);

            $out[] = MailboxUpdate::create(
                Carbon::createFromTimestamp($u['time_ts']),
                $u['body'] ?? '',
                $u['mb_name'] . '@' . PLATFORM_MAILBOX_HOST,
                $u['mailbox_id'],
                $u['id'],
                $u['subject'],
                $sender['mailbox'] . '@' . $sender['host']
            );
        }

        return $out;
    }

    /**
     * @return array<ActivityUpdate>
     */
    private function loadForumUpdates(int $page, array $hidden_ids): array
    {
        $myRegionIds = $this->currentUserUnits->listRegionIDs();

        if (!$myRegionIds) {
            return [];
        }

        $region_ids = [];

        foreach ($myRegionIds as $regionId) {
            if ($regionId > 0 && !isset($hidden_ids[$regionId])) {
                $region_ids[] = $regionId;
            }
        }

        if (count($region_ids) === 0) {
            return [];
        }

        $updates = $this->activityGateway->fetchAllForumUpdates($region_ids, $page, false);

        if ($ambassadorIds = $this->currentUserUnits->getMyAmbassadorRegionIds()) {
            $botPosts = $this->activityGateway->fetchAllForumUpdates($ambassadorIds, $page, true);
            $updates = array_merge($updates, $botPosts);
        }

        if (empty($updates)) {
            return [];
        }

        $activities = [];
        foreach ($updates as $update) {
            $isBot = $update['bot_theme'] === 1;
            $forumTypeString = $isBot ? 'botforum' : 'forum';

            $activities[] = ActivityUpdate::create(
                'forum',
                $this->getCleanedTimestamp($update['update_time_ts']),
                $update['name'],
                $update['post_body'] ?? '',
                $update['bezirk_name'],
                $isBot ? '_bot' : '',
                $update['foodsaver_photo'] ?? '',
                [],
                $update['foodsaver_id'],
                $update['foodsaver_name'],
                $update['id'],
                $update['bezirk_id'],
                $update['last_post_id'],
                $forumTypeString,
            );
        }

        return $activities;
    }

    /**
     * @return array<ActivityUpdate>
     */
    private function loadStoreUpdates(int $page): array
    {
        $updates = $this->activityGateway->fetchAllStoreUpdates($this->session->id(), $page);

        if (empty($updates)) {
            return [];
        }

        $activities = [];
        foreach ($updates as $update) {
            $activities[] = ActivityUpdate::create(
                'store',
                $this->getCleanedTimestamp($update['update_time_ts']),
                $update['betrieb_name'],
                $update['text'] ?? '',
                $update['region_name'],
                '',
                $update['foodsaver_photo'] ?? '',
                [],
                $update['foodsaver_id'],
                $update['foodsaver_name'],
                $update['betrieb_id'],
            );
        }

        return $activities;
    }

    private function getCleanedTimestamp(?string $timestamp): string
    {
        return $timestamp ?? (string)(new DateTime())->getTimestamp();
    }
}
