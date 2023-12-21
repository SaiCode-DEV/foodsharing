<?php

namespace Foodsharing\Modules\Activity;

use Carbon\Carbon;
use Foodsharing\Lib\Session;
use Foodsharing\Modules\Activity\DTO\ActivityFilter;
use Foodsharing\Modules\Activity\DTO\ActivityFilterCategory;
use Foodsharing\Modules\Activity\DTO\ActivityUpdate;
use Foodsharing\Modules\Activity\DTO\ActivityUpdateMailbox as MailboxUpdate;
use Foodsharing\Modules\Activity\DTO\ImageActivityFilter;
use Foodsharing\Modules\Core\DBConstants\Foodsaver\Role;
use Foodsharing\Modules\Core\DBConstants\Unit\UnitType;
use Foodsharing\Modules\Mailbox\MailboxGateway;
use Foodsharing\Utility\ImageHelper;
use Symfony\Contracts\Translation\TranslatorInterface;

class ActivityTransactions
{
    private ActivityGateway $activityGateway;
    private MailboxGateway $mailboxGateway;
    private ImageHelper $imageHelper;
    private TranslatorInterface $translator;
    private Session $session;

    public function __construct(
        ActivityGateway $activityGateway,
        MailboxGateway $mailboxGateway,
        ImageHelper $imageHelper,
        TranslatorInterface $translator,
        Session $session
    ) {
        $this->activityGateway = $activityGateway;
        $this->mailboxGateway = $mailboxGateway;
        $this->imageHelper = $imageHelper;
        $this->translator = $translator;
        $this->session = $session;
    }

    /**
     * Returns all activity filters for the logged in user sorted into categories.
     *
     * @return array ActivityFilterCategory[]
     */
    public function getFilters(): array
    {
        // list of currently excluded activities
        $excluded = $this->session->getOption('activity-listings') ?: [];

        // regions and groups
        $regionOptions = [];
        $groupOptions = [];
        if ($bezirke = $this->session->getRegions()) {
            foreach ($bezirke as $b) {
                $option = ActivityFilter::create($b['name'], $b['id'],
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
            $this->session->isAmbassador(),
            $this->session->id(),
            $this->session->mayRole(Role::STORE_MANAGER))
        ) {
            $mailboxOptions = array_map(function ($b) use ($excluded) {
                return ActivityFilter::create(
                    $b['name'] . '@' . PLATFORM_MAILBOX_HOST, $b['id'],
                    !isset($excluded['mailbox-' . $b['id']])
                );
            }, $boxes);
        }

        // buddy walls
        $buddyOptions = [];
        if ($buddyIds = $this->session->get('buddy-ids')) {
            $buddies = $this->activityGateway->fetchAllBuddies((array)$buddyIds);
            $buddyOptions = array_map(function ($b) use ($excluded) {
                return ImageActivityFilter::create(
                    $b['name'], $b['id'], !isset($excluded['buddywall-' . $b['id']]),
                    $this->imageHelper->img($b['photo'])
                );
            }, $buddies);
        }

        return [
            ActivityFilterCategory::create('bezirk', $this->translator->trans('globals.type.my_groups'),
                $this->translator->trans('globals.type.groups'), $groupOptions),
            ActivityFilterCategory::create('bezirk', $this->translator->trans('globals.type.my_regions'),
                $this->translator->trans('globals.type.regions'), $regionOptions),
            ActivityFilterCategory::create('mailbox', $this->translator->trans('globals.type.my_mailboxes'),
                $this->translator->trans('globals.type.mailboxes'), $mailboxOptions),
            ActivityFilterCategory::create('buddywall', $this->translator->trans('globals.type.my_buddies'),
                $this->translator->trans('globals.type.buddies'), $buddyOptions)
        ];
    }

    /**
     * Sets the deactivated activities for the logged in user.
     *
     * @param array $excluded a list of activities to be deactivated. List entries should be objects with
     * 'index' and 'id' entries
     */
    public function setExcludedFilters(array $excluded): void
    {
        $list = [];
        foreach ($excluded as $o) {
            if (isset($o['index'], $o['id']) && (int)$o['id'] > 0) {
                $list[$o['index'] . '-' . $o['id']] = [
                    'index' => $o['index'],
                    'id' => $o['id']
                ];
            }
        }

        $this->session->setOption('activity-listings', $list);
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
        if ($sesOptions = $this->session->getOption('activity-listings')) {
            foreach ($sesOptions as $o) {
                if (isset($hidden_ids[$o['index']])) {
                    $hidden_ids[$o['index']][$o['id']] = $o['id'];
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

    private function loadEventWallUpdates(int $page): array
    {
        $updates = $this->activityGateway->fetchAllEventUpdates($this->session->id(), $page);
        $out = [];

        foreach ($updates as $u) {
            $out[] = ActivityUpdate::create(
                'event',
                Carbon::createFromTimestamp($u['time_ts']),
                $u['name'],
                $u['body'] ?? '',
                $u['event_region'],
                '',
                $u['fs_photo'] ?? '',
                $u['gallery'] ?? [],
                $u['fs_id'],
                $u['fs_name'],
                $u['event_id']
            );
        }

        return $out;
    }

    private function loadFoodSharePointWallUpdates(int $page): array
    {
        $updates = $this->activityGateway->fetchAllFoodSharePointWallUpdates($this->session->id(), $page);
        $out = [];

        foreach ($updates as $u) {
            $out[] = ActivityUpdate::create(
                'foodsharepoint',
                Carbon::createFromTimestamp($u['time_ts']),
                $u['name'],
                $u['body'] ?? '',
                $u['fsp_location'],
                '',
                $u['fs_photo'] ?? '',
                $u['gallery'] ?? [],
                $u['fs_id'],
                $u['fs_name'],
                $u['fsp_id'],
                $u['region_id']
            );
        }

        return $out;
    }

    private function loadFriendWallUpdates(int $page, array $hidden_ids): array
    {
        $buddy_ids = [];

        if ($b = $this->session->get('buddy-ids')) {
            $buddy_ids = $b;
        }

        $buddy_ids[$this->session->id()] = $this->session->id();

        $bids = [];
        foreach ($buddy_ids as $id) {
            if (!isset($hidden_ids[$id])) {
                $bids[] = $id;
            }
        }

        $updates = $this->activityGateway->fetchAllFriendWallUpdates($bids, $page);
        if (empty($updates)) {
            return [];
        }

        $out = [];
        foreach ($updates as $u) {
            $is_own = $u['fs_id'] === $this->session->id();

            $out[] = ActivityUpdate::create(
                'friendWall',
                Carbon::createFromTimestamp($u['time_ts']),
                '',
                $u['body'] ?? '',
                $u['fs_name'],
                $is_own ? '_own' : '',
                $u['fs_photo'] ?? '',
                $u['gallery'] ?? [],
                $u['fs_id'],
                $u['fs_name'],
                $u['fs_id']
            );
        }

        return $out;
    }

    private function loadMailboxUpdates(int $page, array $hidden_ids): array
    {
        $boxes = $this->mailboxGateway->getBoxes(
            $this->session->isAmbassador(),
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
            $sender = json_decode($u['sender'], true, 512, JSON_THROW_ON_ERROR + JSON_INVALID_UTF8_IGNORE);

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

    private function loadForumUpdates(int $page, array $hidden_ids): array
    {
        $myRegionIds = $this->session->listRegionIDs();

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
        if ($ambassadorIds = $this->session->getMyAmbassadorRegionIds()) {
            $botPosts = $this->activityGateway->fetchAllForumUpdates($ambassadorIds, $page, true);
            $updates = array_merge($updates, $botPosts);
        }

        if (empty($updates)) {
            return [];
        }

        $out = [];
        foreach ($updates as $u) {
            $is_bot = $u['bot_theme'] === 1;

            $forumTypeString = $is_bot ? 'botforum' : 'forum';

            $out[] = ActivityUpdate::create(
                'forum',
                Carbon::createFromTimestamp($u['update_time_ts']),
                $u['name'],
                $u['post_body'] ?? '',
                $u['bezirk_name'],
                $is_bot ? '_bot' : '',
                $u['foodsaver_photo'] ?? '',
                [],
                (int)$u['foodsaver_id'],
                $u['foodsaver_name'],
                (int)$u['id'],
                (int)$u['bezirk_id'],
                (int)$u['last_post_id'],
                $forumTypeString
            );
        }

        return $out;
    }

    private function loadStoreUpdates(int $page): array
    {
        $updates = $this->activityGateway->fetchAllStoreUpdates($this->session->id(), $page);
        if (empty($updates)) {
            return [];
        }

        $out = [];
        foreach ($updates as $u) {
            $out[] = ActivityUpdate::create(
                'store',
                Carbon::createFromTimestamp($u['update_time_ts']),
                $u['betrieb_name'],
                $u['text'] ?? '',
                $u['region_name'],
                '',
                $u['foodsaver_photo'] ?? '',
                null,
                $u['foodsaver_id'],
                $u['foodsaver_name'],
                $u['betrieb_id']
            );
        }

        return $out;
    }
}
