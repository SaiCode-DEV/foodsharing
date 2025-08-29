<?php

namespace Foodsharing\Modules\Core\DBConstants\Bell;

use Foodsharing\Modules\Bell\DTO\Bell;

/**
 * Templates for the identifiers of bells.
 *
 * @see Bell::$identifier
 */
class BellType
{
    /**
     * The user has a new friend request. Arguments: IDs of this user and the sender of the request.
     */
    final public const string BUDDY_REQUEST = 'buddy-%d-%d';
    /**
     * A new post was written on the wall of an FSP which the user is following. Argument: ID of the FSP.
     */
    final public const string FOOD_SHARE_POINT_POST = 'fairteiler-%d';
    /**
     * Notification for ambassadors about a new FSP proposal. Argument: ID of the FSP.
     */
    final public const string NEW_FOOD_SHARE_POINT = 'new-fairteiler-%d';
    /**
     * A new forum post in a thread the user is participating in. Argument: IDs of the thread.
     */
    final public const string NEW_FORUM_POST = 'forum-%d';
    /**
     * A new thread was opened in a moderated forum for which the user is a moderator. Argument: ID of the thread.
     */
    public const NOT_ACTIVATED_FORUM_THREAD = 'forum-thread-activation-%d';
    /**
     * Notification for ambassadors about a new foodsaver. Argument: the foodsaver's ID.
     */
    final public const string NEW_FOODSAVER_IN_REGION = 'new-fs-%d';
    /**
     * Notification to user for the verification status. Argument: the foodsaver's ID.
     */
    final public const string FOODSAVER_VERIFIED = 'fs-verified-%d';
    /**
     * The creation of the foodsaver's pass has failed.
     */
    final public const string PASS_CREATED_OR_RENEWED = 'pass-created-or-renewed-%d';
    /**
     * Notification for a store manager that someone wants to join a store.
     */
    final public const string NEW_STORE_REQUEST = 'store-request-%d';
    /**
     * The user's store request was accepted.
     */
    final public const string STORE_REQUEST_ACCEPTED = 'store-arequest-%d';
    /**
     * The user's store request was rejected.
     */
    final public const string STORE_REQUEST_REJECTED = 'store-drequest-%d';
    /**
     * The user was put on the waiting list (jumper) of a store.
     */
    final public const string STORE_REQUEST_WAITING = 'store-wrequest-%d';
    /**
     * The user was added to a store without a request.
     */
    final public const string STORE_ADDED_WITHOUT_REQUEST = 'store-imposed-%d-%d';
    /**
     * The user was invited to a store. Argument: ID of the store.
     */
    final public const string STORE_INVITATION = 'store-invited-%d';
    /**
     * The user accepted a store invitation. Argument: ID of the store.
     */
    final public const string STORE_INVITATION_ACCEPTED = 'store-invitaton-accepted-%d';
    /**
     * The user declined a store invitation. Argument: ID of the store.
     */
    final public const string STORE_INVITATION_DECLINED = 'store-invitaton-declined-%d';
    /**
     * Notification for a store manager that there are unconfirmed pickups.
     */
    final public const string STORE_UNCONFIRMED_PICKUP = 'store-fetch-unconfirmed-%d';
    /**
     * A new store was created.
     */
    final public const string NEW_STORE = 'store-new-%d';
    /**
     * The pickup times in a store were changed.
     */
    final public const string STORE_TIME_CHANGED = 'store-time-%d';
    /**
     * A new post was written on the wall of a store.
     */
    final public const string STORE_WALL_POST = 'store-wallpost-%d';
    /**
     * A new blog entry is created and needs to be checked.
     */
    final public const string NEW_BLOG_POST = 'blog-check-%d';
    /**
     * A new poll was created in a region or work group.
     */
    final public const string NEW_POLL = 'new-poll-%d';
    /**
     * Sent to the admins of a working group to notify them about a new application.
     * Parameters:
     *   the group's id and
     *   the applicant's id.
     */
    public const WORKING_GROUP_NEW_APPLICATION = 'workinggroup-%d-application-%d';
    /**
     * The user's request to join a work group was accepted.
     */
    final public const string WORK_GROUP_REQUEST_ACCEPTED = 'workgroup-arequest-%d';
    /**
     * The user's request to join a work group was denied.
     */
    final public const string WORK_GROUP_REQUEST_DENIED = 'workgroup-drequest-%d';
    /**
     * A new report for a user was created.
     */
    final public const string NEW_REPORT = 'new-report-%d';

    /**
     * A new report for a user was created.
     */
    final public const string NEW_QUESTION_COMMENT = 'question-comment-%d';

    /**
     * A new post on an event wall the user is maybe participating in. Argument: IDs of the event.
     */
    final public const string NEW_EVENT_POST = 'event-post-%d';

    /**
     * A new banana was given to the user. Argument: IDs of the recipient and sender.
     */
    final public const string BANANA = 'banana-%d-%d';

    /**
     * A new forum post in a thread the user is participating in. Argument: ID of the post.
     */
    final public const string FORUM_MENTION = 'forum-mention-%d';

    /**
     * A forum post of the user was hidden. Argument: ID of the post.
     */
    final public const string FORUM_POST_HIDDEN = 'forum-post-hidden-%d';

    /**
     * A new thread was opened in a forum the user is actively following. Argument: ID of the thread.
     */
    final public const string NEW_FORUM_THREAD = 'new-thread-%d';

    /**
     * A new resource was added to the user's region. Argument: ID of the region.
     */
    final public const string NEW_RESOURCE = 'new-resource-%d';

    /**
     * Creates a bell identifier from a template and an optional list of parameters.
     */
    public static function createIdentifier(string $typeString, ...$params)
    {
        return sprintf($typeString, ...$params);
    }
}
