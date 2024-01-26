<?php

namespace Foodsharing\RestApi\Models\Store;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Represents all information which are required for creation of a new store.
 */
class CreateStoreModel
{
    /**
     * Information about the new store.
     */
    #[Assert\NotNull]
    #[Assert\Valid]
    public ?CreateStoreInformationModel $store = null;

    /**
     * Optional first comment in store wall of the new store.
     */
    #[Assert\Length(max: 180)]
    public ?string $firstPost = null;
}
