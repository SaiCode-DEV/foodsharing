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
    final public const BUDDY_REQUEST = 'buddy-%d-%d';
    /**
     * A new post was written on the wall of an FSP which the user is following. Argument: ID of the FSP.
     */
    final public const FOOD_SHARE_POINT_POST = 'fairteiler-%d';
    /**
     * Notification for ambassadors about a new FSP proposal. Argument: ID of the FSP.
     */
    final public const NEW_FOOD_SHARE_POINT = 'new-fairteiler-%d';
    /**
     * A new forum post in a thread the user is participating in. Argument: IDs of the thread.
     */
    final public const NEW_FORUM_POST = 'forum-%d';
    /**
     * A new thread was opened in a moderated forum for which the user is a moderator. Argument: ID of the thread.
     */
    public const NOT_ACTIVATED_FORUM_THREAD = 'forum-thread-activation-%d';
    /**
     * Notification for ambassadors about a new foodsaver. Argument: the foodsaver's ID.
     */
    final public const NEW_FOODSAVER_IN_REGION = 'new-fs-%d';
    /**
     * Notification to user for the verification status. Argument: the foodsaver's ID.
     */
    final public const FOODSAVER_VERIFIED = 'fs-verified-%d';
    /**
     * The creation of the foodsaver's pass has failed.
     */
    final public const PASS_CREATION_FAILED = 'pass-fail-%d';
    /**
     * Notification for a store manager that someone wants to join a store.
     */
    final public const NEW_STORE_REQUEST = 'store-request-%d';
    /**
     * The user's store request was accepted.
     */
    final public const STORE_REQUEST_ACCEPTED = 'store-arequest-%d';
    /**
     * The user's store request was rejected.
     */
    final public const STORE_REQUEST_REJECTED = 'store-drequest-%d';
    /**
     * The user was put on the waiting list (jumper) of a store.
     */
    final public const STORE_REQUEST_WAITING = 'store-wrequest-%d';
    /**
     * The user was added to a store without a request.
     */
    final public const STORE_ADDED_WITHOUT_REQUEST = 'store-imposed-%d-%d';
    /**
     * Notification for a store manager that there are unconfirmed pickups.
     */
    final public const STORE_UNCONFIRMED_PICKUP = 'store-fetch-unconfirmed-%d';
    /**
     * A new store was created.
     */
    final public const NEW_STORE = 'store-new-%d';
    /**
     * The pickup times in a store were changed.
     */
    final public const STORE_TIME_CHANGED = 'store-time-%d';
    /**
     * A new post was written on the wall of a store.
     */
    final public const STORE_WALL_POST = 'store-wallpost-%d';
    /**
     * A new blog entry is created and needs to be checked.
     */
    final public const NEW_BLOG_POST = 'blog-check-%d';
    /**
     * A new poll was created in a region or work group.
     */
    final public const NEW_POLL = 'new-poll-%d';
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
    final public const WORK_GROUP_REQUEST_ACCEPTED = 'workgroup-arequest-%d';
    /**
     * The user's request to join a work group was denied.
     */
    final public const WORK_GROUP_REQUEST_DENIED = 'workgroup-drequest-%d';
    /**
     * A new report for a user was created.
     */
    final public const NEW_REPORT = 'new-report-%d';

    /**
     * A new report for a user was created.
     */
    final public const NEW_QUESTION_COMMENT = 'question-comment-%d';

    /**
     * A new post on an event wall the user is maybe participating in. Argument: IDs of the event.
     */
    final public const NEW_EVENT_POST = 'event-post-%d';

    /**
     * A new banana was given to the user. Argument: IDs of the recipient and sender.
     */
    final public const BANANA = 'banana-%d-%d';

    /**
     * A new forum post in a thread the user is participating in. Argument: ID of the thread.
     */
    final public const FORUM_MENTION = 'forum-mention-%d';

    /**
     * Creates a bell identifier from a template and an optional list of parameters.
     */
    public static function createIdentifier(string $typeString, ...$params)
    {
        return sprintf($typeString, ...$params);
    }
}
