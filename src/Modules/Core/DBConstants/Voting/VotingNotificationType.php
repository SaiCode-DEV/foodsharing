<?php

namespace Foodsharing\Modules\Core\DBConstants\Voting;

/**
 * Constants for the notification status of polls.
 * This enum represents the possible values for the notifications_sent column in fs_poll table.
 */
class VotingNotificationType
{
    /**
     * No notifications have been sent yet.
     */
    final public const int NONE = 0;

    /**
     * Start notification has been sent, but end notification has not yet been sent.
     */
    final public const int START_SENT = 1;

    /**
     * Both start and end notifications have been sent.
     */
    final public const int BOTH_SENT = 2;
}
