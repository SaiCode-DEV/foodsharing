<?php

namespace Foodsharing\Modules\Core\DBConstants;

/**
 * Represents different types of categories.
 */
enum CategoryType: string
{
    /**
     * The categories for stores.
     * Each store has 1 category or none.
     */
    case STORE = 'store';

    /**
     * The categories for resources.
     * Each resource can have no, one or multiple categories.
     */
    case RESOURCE = 'resource';

    /**
     * The categories for groups.
     * Each group has one category or none.
     */
    case GROUP = 'group';
}
