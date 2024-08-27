<?php

namespace Foodsharing\Modules\Core\DBConstants;

/**
 * Represents on which page a wall is visible.
 */
enum WallType: string
{
    /**
     * Visible on the start page of a working group.
     */
    case WORKING_GROUP = 'bezirk';
    /**
     * Visible on an event's page.
     */
    case EVENT = 'event';
    /**
     * On a food-share point's page.
     */
    case FOOD_SHARE_POINT = 'fairteiler';
    /**
     * The wall is attached to a quiz question. Users can give feedback to a question. The quiz group can see this
     * wall to improve the quiz.
     */
    case QUIZ_QUESTION = 'question';
    /**
     * The personal wall that is visible on each profile page.
     */
    case PROFILE = 'foodsaver';
    /**
     * Notes that are attached to a profile. Only visible to Orga, not by the users themselves.
     */
    case PROFILE_NOTES = 'usernotes';
    /**
     * The wall is attached to personal reports about a user.
     */
    case FOODSAVER_REPORTS = 'fsreports';
    /**
     * The wall is attached to a store.
     */
    case STORE = 'store';
}
