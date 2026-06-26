<?php

namespace Foodsharing\Modules\Core\DBConstants\Foodsaver;

/**
 * Values for fs_foodsaver_change_history.object_name.
 *
 * The table fs_foodsaver_change_history is a log for changes in a user's profile settings. The column 'object_name'
 * can either be one of the columns in fs_foodsaver (e.g. email, name, ...) or one of the constants in this class.
 */
enum ChangeHistoryKey: string
{
    /**
     * A request was made to change the user's login email address by orga.
     */
    case CHANGE_EMAIL_REQUEST = 'mailchange_request';
    /**
     * The user's new email address was verified. The request to change the login email address was thereby completed.
     */
    case CHANGE_EMAIL_REQUEST_COMPLETED = 'email';
    /**
     * The user aborted the request to change the login email address.
     */
    case CHANGE_EMAIL_REQUEST_ABORTED = 'emailAbort';
    /**
     * The user joined a region.
     */
    case JOINED_REGION = 'bezirk_id';
}
