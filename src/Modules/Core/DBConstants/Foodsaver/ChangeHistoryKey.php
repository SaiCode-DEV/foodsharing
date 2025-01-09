<?php

namespace Foodsharing\Modules\Core\DBConstants\Foodsaver;

/**
 * Values for fs_foodsaver_change_history.object_name.
 *
 * The table fs_foodsaver_change_history is a log for changes in a user's profile settings. The column 'object_name'
 * can either be one of the columns in fs_foodsaver (e.g. email, name, ...) or one of the constants in this class.
 */
class ChangeHistoryKey
{
    /**
     * Denotes that a request was made to change the user's login email address by orga.
     */
    final public const string CHANGE_EMAIL_REQUEST = 'mailchange_request';
}
