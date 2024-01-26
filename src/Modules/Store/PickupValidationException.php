<?php

namespace Foodsharing\Modules\Store;

class PickupValidationException extends \InvalidArgumentException
{
    final public const MAX_SLOT_COUNT_OUT_OF_RANGE = 'Slot value out of range.';
    final public const DUPLICATE_PICKUP_DAY_TIME = 'Multiply pickups for the same day and time.';
    final public const PICK_UP_DATE_IN_THE_PAST = 'Pickup is in the past.';
    final public const SLOT_COUNT_OUT_OF_RANGE = 'Slot value out of range.';
    final public const DESCRIPTION_OVERSIZED = 'Description is too long.';
    final public const MORE_OCCUPIED_SLOTS = 'Slot value is smaller then occuied slots.';
    final public const INVALID_STORE = 'Store does not exist.';

    public function __construct(string $message = '', int $code = 0)
    {
        parent::__construct($message, $code);
    }

    public function __toString(): string
    {
        return self::class . ": [{$this->code}]: {$this->message}\n";
    }
}
