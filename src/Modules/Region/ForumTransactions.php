<?php

namespace Foodsharing\Modules\Region;

use Foodsharing\Lib\Session;
use Foodsharing\Modules\Bell\BellGateway;
use Foodsharing\Modules\Bell\BellTransactions;
use Foodsharing\Modules\Bell\DTO\Bell;
use Foodsharing\Modules\Core\DBConstants\Bell\BellType;
use Foodsharing\Modules\Core\DBConstants\Foodsaver\UserOptionType;
use Foodsharing\Modules\Core\DBConstants\Info\InfoType;
use Foodsharing\Modules\Core\DBConstants\Region\WorkgroupFunction;
use Foodsharing\Modules\Core\DBConstants\Unit\UnitType;
use Foodsharing\Modules\Foodsaver\FoodsaverGateway;
use Foodsharing\Modules\Group\GroupFunctionGateway;
use Foodsharing\Modules\Reaction\ReactionTransactions;
use Foodsharing\Modules\Region\DTO\ForumPost;
use Foodsharing\Modules\Region\DTO\ForumThread;
use Foodsharing\Modules\Region\DTO\ForumThreadPermissions;
use Foodsharing\Modules\Region\Exceptions\NoVisiblePostException;
use Foodsharing\Modules\Settings\SettingsGateway;
use Foodsharing\Modules\Unit\CurrentUserUnitsInterface;
use Foodsharing\Permissions\ForumPermissions;
use Foodsharing\RestApi\Models\Notifications\Thread;
use Foodsharing\Utility\EmailHelper;
use Foodsharing\Utility\FlashMessageHelper;
use Foodsharing\Utility\Sanitizer;
use Symfony\Contracts\Translation\TranslatorInterface;

class ForumTransactions
{
    public function __construct(
        private readonly FoodsaverGateway $foodsaverGateway,
        private readonly ForumGateway $forumGateway,
        private readonly ForumFollowerGateway $forumFollowerGateway,
        private readonly Session $session,
        private readonly RegionGateway $regionGateway,
        private readonly Sanitizer $sanitizerService,
        private readonly EmailHelper $emailHelper,
        private readonly FlashMessageHelper $flashMessageHelper,
        private readonly TranslatorInterface $translator,
        private readonly GroupFunctionGateway $groupFunctionGateway,
        private readonly BellTransactions $bellTransactions,
        private readonly BellGateway $bellGateway,
        private readonly SettingsGateway $settingsGateway,
        private readonly ReactionTransactions $reactionTransactions,
        private readonly CurrentUserUnitsInterface $currentUserUnits,
        private readonly ForumPermissions $forumPermissions,
    ) {
    }

    public function url($regionId, $ambassadorForum, $threadId = null, $postId = null): string
    {
        $url = '/region?bid=' . $regionId . '&sub=' . ($ambassadorForum ? 'botforum' : 'forum');
        if ($threadId) {
            $url .= '&tid=' . $threadId;
        }
        if ($postId) {
            $url .= '&pid=' . $postId;
        }

        return $url;
    }

    public function addPostToThread(int $foodsaverId, int $threadId, string $body): int
    {
        $rawBody = $body;
        $pid = $this->forumGateway->addPost($foodsaverId, $threadId, $body);

        $this->notifyFollowersViaMail($threadId, $rawBody, $foodsaverId, $pid);
        $this->bellTransactions->addGroupedBellEvent(...$this->getGroupedBellEventData($threadId, $pid, $foodsaverId));
        $this->sendNotificationsToMentionedUsers($threadId, $pid, $body);

        return $pid;
    }

    public function deletePostFromThread(int $postId, int $authorId): void
    {
        $threadId = $this->forumGateway->getThreadForPost($postId);
        $this->bellTransactions->removeGroupedBellEvent(...$this->getGroupedBellEventData($threadId, $postId, $authorId));
        $this->bellGateway->delBellsByIdentifier(BellType::createIdentifier(BellType::FORUM_MENTION, $postId));
        $this->forumGateway->deletePost($postId);
        try {
            $this->forumGateway->updateLastPostId($threadId);
        } catch (NoVisiblePostException) {
            $posts = $this->forumGateway->listPosts($threadId);
            if (count($posts)) {
                $this->forumGateway->setLastPostId($threadId, end($posts)->id);

                // TODO for future MR:
                // Mark whole thread as hidden
                return;
            }
            $this->forumGateway->deleteThread($threadId);
        }
    }

    /**
     * @return ForumPost[]
     */
    public function listPostsWithReactions(int $threadId): array
    {
        $posts = $this->forumGateway->listPosts($threadId);
        $postIds = array_map(fn ($post) => $post->id, $posts);
        $reactions = $this->forumGateway->getReactionsForPosts($postIds);
        $this->reactionTransactions->addReactionsToPosts($reactions, $posts);

        return $posts;
    }

    public function hidePost(int $postId, int $moderatorId, string $reason): void
    {
        // Bell for affected user:
        $threadId = $this->forumGateway->getThreadIdForPost($postId);
        $authorId = $this->forumGateway->getPost($postId)['author_id'];
        $info = $this->forumGateway->getThreadInfo($threadId);
        $regionName = $this->regionGateway->getRegionName($info['region_id']);
        $bell = Bell::create(
            'post_hidden_title',
            'post_hidden',
            'fas fa-eye-slash',
            ['href' => $this->url($info['region_id'], $info['ambassador_forum'], $threadId, $postId)],
            [
                'forum' => $regionName,
                'title' => $info['title'],
                'user' => $this->session->user('name'),
            ],
            BellType::createIdentifier(BellType::FORUM_POST_HIDDEN, $postId)
        );
        $this->bellGateway->addBell($authorId, $bell);

        $this->bellTransactions->removeGroupedBellEvent(...$this->getGroupedBellEventData($threadId, $postId, $moderatorId));
        $this->forumGateway->hidePost($postId, $moderatorId, $reason);
        try {
            $this->forumGateway->updateLastPostId($threadId);
        } catch (NoVisiblePostException) {
            // TODO for future MR:
            // Mark whole thread as hidden.
        }
    }

    private function getGroupedBellEventData(int $threadId, int $postId, int $authorId)
    {
        $followerIds = array_column($this->forumFollowerGateway->getThreadFollower($authorId, $threadId, InfoType::BELL), 'id');
        $info = $this->forumGateway->getThreadInfo($threadId);
        $regionName = $this->regionGateway->getRegionName($info['region_id']);
        $baseBell = Bell::create(
            'forum_post_title',
            'forum_post',
            'fas fa-comment',
            ['href' => $this->url($info['region_id'], $info['ambassador_forum'], $threadId, $postId)],
            [
                'forum' => $regionName,
                'title' => $info['title'],
                'user' => $this->session->user('name'),
            ],
            BellType::createIdentifier(BellType::NEW_FORUM_POST, $threadId)
        );

        return [$followerIds, $baseBell, $postId, 'fas fa-comments'];
    }

    public function createThread($fsId, $title, $body, $region, $ambassadorForum, $isActive, $sendMail)
    {
        $threadId = $this->forumGateway->addThread($fsId, $region['id'], $title, $body, $isActive, $ambassadorForum);
        if (!$isActive) {
            $this->notifyAdminsModeratedThread($region, $threadId, $body);
        } else {
            if ($sendMail) {
                $this->notifyMembersOfForumAboutNewThreadViaMail($region, $threadId, $ambassadorForum);
            } else {
                $this->flashMessageHelper->info($this->translator->trans('forum.thread.no_mail'));
            }

            $this->notifyActiveFollowersOfForumAboutNewThreadViaBell($region, $threadId, $ambassadorForum, $title);
        }

        $this->sendNotificationsToMentionedUsers($threadId, null, $body);

        return $threadId;
    }

    private function sendNotificationMail(array $recipients, string $template, array $data): void
    {
        foreach ($recipients as $recipient) {
            $this->emailHelper->tplMail(
                $template,
                $recipient['email'],
                array_merge($data, [
                    'anrede' => $this->translator->trans('salutation.' . $recipient['geschlecht']),
                    'name' => $recipient['name'],
                ])
            );
        }
    }

    public function notifyFollowersViaMail($threadId, $rawPostBody, $postFrom, $postId): void
    {
        if ($follower = $this->forumFollowerGateway->getThreadFollower($postFrom, $threadId, InfoType::EMAIL)) {
            $info = $this->forumGateway->getThreadInfo($threadId);
            $posterName = $this->foodsaverGateway->getFoodsaverName($this->session->id());
            $data = [
                'link' => BASE_URL . $this->url($info['region_id'], $info['ambassador_forum'], $threadId, $postId),
                'thread' => $info['title'],
                'post' => $this->sanitizerService->markdownToHtml($rawPostBody),
                'poster' => $posterName
            ];
            $this->sendNotificationMail($follower, 'forum/answer', $data);
        }
    }

    private function notifyAdminsModeratedThread($region, $threadId, $rawPostBody): void
    {
        $thread = $this->forumGateway->getThread($threadId);
        $posterName = $this->foodsaverGateway->getFoodsaverName($thread->creatorId);
        $moderationGroup = $this->groupFunctionGateway->getRegionFunctionGroupId($region['id'], WorkgroupFunction::MODERATION);
        if (empty($moderationGroup)) {
            $moderators = $this->foodsaverGateway->getAdminsOrAmbassadors($region['id']);
        } else {
            $moderators = $this->foodsaverGateway->getAdminsOrAmbassadors($moderationGroup);
        }
        if ($moderators) {
            // send notification e-mail
            $link = BASE_URL . $this->url($region['id'], false, $threadId);
            $data = [
                'link' => $link,
                'thread' => $thread->title,
                'post' => $this->sanitizerService->markdownToHtml($rawPostBody),
                'poster' => $posterName,
                'bezirk' => $region['name'],
                'confirmation_translation_key' => 'email_template.confirm_for_' . ($region['moderated'] ? 'moderated_forum' : 'unverified'),
            ];

            $this->sendNotificationMail($moderators, 'forum/activation', $data);

            // create notification bell
            $bellData = Bell::create(
                'forum_not_activated_thread_title',
                'forum_not_activated_thread',
                'fas fa-comments',
                ['href' => $link],
                [
                    'user' => $this->session->user('name'),
                    'forum' => $region['name'],
                    'title' => $thread->title,
                ],
                BellType::createIdentifier(BellType::NOT_ACTIVATED_FORUM_THREAD, $threadId),
                false,
            );
            $this->bellGateway->addBell(array_column($moderators, 'id'), $bellData);
        }
    }

    private function notifyMembersOfForumAboutNewThreadViaMail(array $regionData, int $threadId, bool $isAmbassadorForum): void
    {
        $regionType = $this->regionGateway->getType($regionData['id']);
        if (!$isAmbassadorForum && in_array($regionType, [UnitType::COUNTRY, UnitType::FEDERAL_STATE])) {
            $this->flashMessageHelper->info($this->translator->trans('forum.thread.too_big_to_mail'));

            return;
        } else {
            $this->flashMessageHelper->info($this->translator->trans('forum.thread.with_mail'));
        }

        $thread = $this->forumGateway->getThread($threadId);
        $body = $this->forumGateway->getPost($thread->lastPostId)['body'];

        $posterName = $this->foodsaverGateway->getFoodsaverName($thread->creatorId);

        if ($isAmbassadorForum) {
            $recipients = $this->foodsaverGateway->getAdminsOrAmbassadors($regionData['id']);
        } else {
            $recipients = $this->foodsaverGateway->listActiveWithFullNameByRegion($regionData['id']);
        }

        $data = [
            'bezirk' => $regionData['name'],
            'poster' => $posterName,
            'thread' => $thread->title,
            'link' => BASE_URL . $this->url($regionData['id'], $isAmbassadorForum, $threadId),
            'post' => $this->sanitizerService->markdownToHtml($body),
            ];
        $this->sendNotificationMail($recipients,
            $isAmbassadorForum ? 'forum/new_region_ambassador_message' : 'forum/new_message', $data);
    }

    private function notifyActiveFollowersOfForumAboutNewThreadViaBell(array $region, int $threadId, bool $isAmbassadorForum, string $title): void
    {
        $recipients = $this->forumFollowerGateway->getAdminIdsToNotifyForNewThread($region['id']);
        if (!$isAmbassadorForum) {
            $recipients = array_unique(array_merge($recipients, $this->forumFollowerGateway->getUserIdsToNotifyForNewThread($region['id'])));
        }
        $recipients = array_diff($recipients, [$this->session->id()]); // exclude author

        $bell = Bell::create(
            'new_forum_thread_title',
            'new_forum_thread',
            'fas fa-comment-medical',
            ['href' => $this->url($region['id'], $isAmbassadorForum, $threadId)],
            [
                'forum' => $region['name'],
                'title' => $title,
                'author' => $this->session->user('name'),
            ],
            BellType::createIdentifier(BellType::NEW_FORUM_THREAD, $threadId)
        );

        $this->bellGateway->addBell($recipients, $bell);
    }

    public function addReaction($fsId, $postId, $key): void
    {
        if (!$fsId || !$postId || !$key) {
            throw new \InvalidArgumentException();
        }
        $this->forumGateway->addReaction($postId, $fsId, $key);
    }

    public function removeReaction($fsId, $postId, $key): void
    {
        if (!$fsId || !$postId || !$key) {
            throw new \InvalidArgumentException();
        }
        $this->forumGateway->removeReaction($postId, $fsId, $key);
    }

    /**
     * Updates the user's notification settings for a list of forum threads individually.
     *
     * @param Thread[] $threads
     */
    public function updateThreadNotifications(int $userId, array $threads): void
    {
        foreach ($threads as $thread) {
            $threadIdsToUnfollow = [];

            if ($thread->infotype == InfoType::NONE) {
                $threadIdsToUnfollow[] = $thread->id;
            }
            $this->forumFollowerGateway->updateInfoType($userId, $thread->id, $thread->infotype);
        }

        if (!empty($threadIdsToUnfollow)) {
            foreach ($threadIdsToUnfollow as $threadId) {
                $this->forumFollowerGateway->unfollowThreadByEmail($userId, $threadId);
            }
        }
    }

    public function restorePost(int $postId): bool
    {
        $deleted = $this->forumGateway->restorePost($postId);
        if ($deleted) {
            $threadId = $this->forumGateway->getThreadIdForPost($postId);
            $this->forumGateway->updateLastPostId($threadId);
        }

        return $deleted;
    }

    private function sendNotificationsToMentionedUsers(int $threadId, ?int $postId, string $postBody): void
    {
        // Get mentioned users using regex to find occurrences of @ followed by digits, but not preceded or followed by letters or digits
        preg_match_all('/(?<![a-zA-Z0-9])@(\d+)(?![a-zA-Z0-9])/', $postBody, $matches);
        $mentionedUsers = array_map('intval', $matches[1]);
        $mentionedUsers = array_diff($mentionedUsers, [$this->session->id()]);
        $mentionedUsers = array_unique($mentionedUsers);

        if (empty($mentionedUsers)) {
            return;
        }

        $userOptions = $this->settingsGateway->getUsersOption($mentionedUsers, UserOptionType::DISABLE_MENTION_NOTIFICATION);
        $usersWithNotificationsTurnedOn = array_map(fn ($item) => $item['userId'], array_filter($userOptions, fn ($item) => !$item['option']));

        $info = $this->forumGateway->getThreadInfo($threadId);
        $regionId = $info['region_id'];
        $regionName = $this->regionGateway->getRegionName($regionId);

        $notifiedUsers = array_filter($usersWithNotificationsTurnedOn, fn ($userId) => $this->regionGateway->hasMember($userId, $regionId));

        if (empty($notifiedUsers)) {
            return;
        }

        $bell = Bell::create(
            'forum_mention_title',
            'forum_mention',
            'fas fa-at',
            ['href' => $this->url($regionId, $info['ambassador_forum'], $threadId, $postId)],
            [
                'forum' => $regionName,
                'title' => $info['title'],
                'user' => $this->session->user('name'),
            ],
            BellType::createIdentifier(BellType::FORUM_MENTION, $postId)
        );

        $this->bellGateway->addBellForUsers($notifiedUsers, $bell);
    }

    /**
     * Activates a thread in a moderated forum. This function does nothing if the thread is already activated.
     */
    public function activateThread(int $threadId): void
    {
        $this->forumGateway->activateThread($threadId);
        $this->removeInactiveThreadBell($threadId);
    }

    /**
     * Deletes a thread. Removes the corresponding bell notifications, if the thread was not yet activated. This
     * function does nothing if the thread does not exist.
     */
    public function deleteThread(int $threadId): void
    {
        $this->forumGateway->deleteThread($threadId);
        $this->removeInactiveThreadBell($threadId);
    }

    /**
     * Removes the bell that was created to notify moderators about a new thread. This function does nothing if
     * the thread or the bell do not exist.
     *
     * @param int $threadId the thread for which the bell was created
     */
    private function removeInactiveThreadBell(int $threadId): void
    {
        $identifier = BellType::createIdentifier(BellType::NOT_ACTIVATED_FORUM_THREAD, $threadId);
        if ($this->bellGateway->bellWithIdentifierExists($identifier)) {
            $this->bellGateway->delBellsByIdentifier($identifier);
        }
    }

    public function isFollowingForum($regionId): bool
    {
        $isFollowing = $this->forumFollowerGateway->isFollowingForum($regionId, $this->session->id());
        if (is_null($isFollowing)) {
            $isFollowing = $this->currentUserUnits->isAdminFor($regionId);
        }

        return $isFollowing;
    }

    public function getFullThread($threadId): ForumThread
    {
        $thread = $this->forumGateway->getThread($threadId);
        $thread->permissions = new ForumThreadPermissions();
        $thread->permissions->mayModerate = $this->forumPermissions->mayModerate($threadId);
        $thread->permissions->mayHidePosts = $this->forumPermissions->mayHidePosts($threadId);
        $thread->permissions->mayDelete = $this->forumPermissions->mayDeletePosts($threadId);
        $thread->subscriptionsStatus = $this->forumFollowerGateway->getThreadSubscriptionsStatus($threadId, $this->session->id());
        $thread->posts = $this->listPostsWithReactions($threadId);

        if (!$thread->permissions->mayModerate) {
            foreach ($thread->posts as &$post) {
                if (!is_null($post->hidden)) {
                    $post->body = null;
                }
            }
        }

        return $thread;
    }
}
