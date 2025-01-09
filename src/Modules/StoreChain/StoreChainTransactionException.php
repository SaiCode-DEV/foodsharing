<?php

namespace Foodsharing\Modules\StoreChain;

class StoreChainTransactionException extends \Exception
{
    final public const string INVALID_STORECHAIN_ID = 'INVALID_STORECHAIN_ID';
    final public const string KEY_ACCOUNT_MANAGER_ID_NOT_EXISTS = 'KEY_ACCOUNT_MANAGER_ID_NOT_EXISTS';
    final public const string KEY_ACCOUNT_MANAGER_ID_NOT_IN_GROUP = 'KEY_ACCOUNT_MANAGER_ID_NOT_IN_GROUP';
    final public const string THREAD_ID_NOT_EXISTS = 'THREAD_ID_NOT_EXISTS';
    final public const string WRONG_FORUM = 'WRONG_FORUM';
    final public const string EMPTY_NAME = 'EMPTY_NAME';
    final public const string EMPTY_CITY = 'EMPTY_CITY';
    final public const string EMPTY_COUNTRY = 'EMPTY_COUNTRY';
    final public const string EMPTY_ZIP = 'EMPTY_ZIP';
    final public const string INVALID_STATUS = 'INVALID_STATUS';

    public function __construct(string $message = '', int $code = 0)
    {
        parent::__construct($message, $code);
    }

    public function __toString(): string
    {
        return self::class . ": [{$this->code}]: {$this->message}\n";
    }
}
