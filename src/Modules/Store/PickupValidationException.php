<?php

namespace Foodsharing\Modules\Store;

class PickupValidationException extends \InvalidArgumentException
{
    public const MAX_SLOT_COUNT_OUT_OF_RANGE = 'Slot value out of range.';
    public const DUPLICATE_PICKUP_DAY_TIME = 'Multiply pickups for the same day and time.';
    public const PICK_UP_DATE_IN_THE_PAST = 'Pickup is in the past.';
    public const SLOT_COUNT_OUT_OF_RANGE = 'Slot value out of range.';
    public const DESCRIPTION_OVERSIZED = 'Description is too long.';
    public const MORE_OCCUPIED_SLOTS = 'Slot value is smaller then occuied slots.';
    public const INVALID_STORE = 'Store does not exist.';

    public function __construct(string $message = '', int $code = 0)
    {
        parent::__construct($message, $code);
    }

    public function __toString(): string
    {
        return self::class . ": [{$this->code}]: {$this->message}\n";
    }
}
