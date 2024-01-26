<?php

namespace Foodsharing\Modules\StoreChain;

class StoreChainTransactionException extends \Exception
{
    public const INVALID_STORECHAIN_ID = 'INVALID_STORECHAIN_ID';
    public const KEY_ACCOUNT_MANAGER_ID_NOT_EXISTS = 'KEY_ACCOUNT_MANAGER_ID_NOT_EXISTS';
    public const KEY_ACCOUNT_MANAGER_ID_NOT_IN_GROUP = 'KEY_ACCOUNT_MANAGER_ID_NOT_IN_GROUP';
    public const THREAD_ID_NOT_EXISTS = 'THREAD_ID_NOT_EXISTS';
    public const WRONG_FORUM = 'WRONG_FORUM';
    public const EMPTY_NAME = 'EMPTY_NAME';
    public const EMPTY_CITY = 'EMPTY_CITY';
    public const EMPTY_COUNTRY = 'EMPTY_COUNTRY';
    public const EMPTY_ZIP = 'EMPTY_ZIP';
    public const INVALID_STATUS = 'INVALID_STATUS';

    public function __construct(string $message = '', int $code = 0)
    {
        parent::__construct($message, $code);
    }

    public function __toString(): string
    {
        return self::class . ": [{$this->code}]: {$this->message}\n";
    }
}
