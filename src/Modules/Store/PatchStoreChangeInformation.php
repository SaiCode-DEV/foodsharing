<?php

namespace Foodsharing\Modules\Store;

/**
 * Class provides information about the done modification patching the store.
 */
class PatchStoreChangeInformation
{
    public bool $informationChanged = false;

    public bool $nameChanged = false;

    public bool $groceriesChanged = false;

    public bool $chainChanged = false;
}
