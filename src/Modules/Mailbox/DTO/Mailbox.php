<?php

namespace Foodsharing\Modules\Mailbox\DTO;

/**
 * Only the most basic information about a mailbox. This does not include what kind of mailbox it is (personal,
 * region, ...).
 */
class Mailbox
{
    public int $id;

    /**
     * Email address without the domain.
     */
    public string $address;

    /**
     * An optional descriptive name that can be shown instead of the email address. This name might differ from the
     * address.
     */
    public ?string $name = null;

    public static function create(int $id, string $address, ?string $name = null): Mailbox
    {
        $m = new Mailbox();
        $m->id = $id;
        $m->address = $address;
        $m->name = $name;

        return $m;
    }
}
