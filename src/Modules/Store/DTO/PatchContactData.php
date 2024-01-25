<?php

namespace Foodsharing\Modules\Store\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class PatchContactData
{
    /**
     * String with name of contact person for store.
     */
    #[Assert\Length(max: 60)]
    public ?string $name = null;

    /**
     * String with phone number of contact person for store.
     * // Check phone number format?
     */
    #[Assert\Length(max: 50)]
    public ?string $phone = null;

    /**
     * String with fax number of contact person for store.
     * // Check phone number format?
     */
    #[Assert\Length(max: 50)]
    public ?string $fax = null;

    /**
     * String with e-mail of contact person for store.
     * // Check email format?
     */
    #[Assert\Length(max: 60)]
    public ?string $email = null;

    public static function apply(PatchContactData &$contactChange, ContactData &$storeContact): bool
    {
        $patchNeeded = false;
        if (!is_null($contactChange->name)) {
            $patchNeeded = true;
            $storeContact->name = $contactChange->name;
        }

        if (!is_null($contactChange->phone)) {
            $patchNeeded = true;
            $storeContact->phone = $contactChange->phone;
        }

        if (!is_null($contactChange->fax)) {
            $patchNeeded = true;
            $storeContact->fax = $contactChange->fax;
        }

        if (!is_null($contactChange->email)) {
            $patchNeeded = true;
            $storeContact->email = $contactChange->email;
        }

        return $patchNeeded;
    }
}
