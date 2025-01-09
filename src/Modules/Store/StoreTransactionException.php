<?php

namespace Foodsharing\Modules\Store;

class StoreTransactionException extends \Exception
{
    final public const string NO_PICKUP_SLOT_AVAILABLE = 'No pickup slot available';
    final public const string NO_PICKUP_OTHER_USER = 'No pickup for another users.';
    final public const string STORE_CATEGORY_NOT_EXISTS = 'Store category does not exists.';
    final public const string STORE_CHAIN_NOT_EXISTS = 'Store chain does not exists.';
    final public const string INVALID_STORE_TEAM_STATUS = 'Team status is invalid';
    final public const string INVALID_PUBLIC_TIMES = 'Public time is invalid';
    final public const string INVALID_COOPERATION_STATUS = 'Cooperation status is invalid.';
    final public const string INVALID_CONVINCE_STATUS = 'Effort convince status is invalid.';
    final public const string INVALID_REGION = 'Store is in an unknown region';
    final public const string INVALID_REGION_TYPE = 'Region type is wrong';
    final public const string INVALID_STORE_COOPERATION_START = 'Store cooperation start is in wrong format';

    final public const string INVALID_STICKER_STATUS = 'Sticker status is invalid.';

    final public const string INVALID_PUBLICITY_STATUS = 'Publicity status is invalid.';

    public function __construct(string $message = '', int $code = 0)
    {
        parent::__construct($message, $code);
    }

    public function __toString(): string
    {
        return self::class . ": [{$this->code}]: {$this->message}\n";
    }
}
