<?php

namespace Foodsharing\Permissions;

use Foodsharing\Lib\Session;
use Foodsharing\Modules\Core\DBConstants\Foodsaver\Role;
use Foodsharing\Modules\Core\DBConstants\Region\RegionIDs;
use Foodsharing\Modules\Region\RegionGateway;
use Foodsharing\Modules\Store\StoreGateway;
use Foodsharing\Modules\StoreChain\StoreChainGateway;
use Foodsharing\Modules\Unit\CurrentUserUnitsInterface;

class StoreChainPermissions
{
    public function __construct(
        private readonly Session $session,
        private readonly StoreChainGateway $gateway,
        private readonly StoreGateway $storeGateway,
        private readonly RegionGateway $regionGateway,
        private readonly CurrentUserUnitsInterface $currentUserUnits,
    ) {
    }

    public function mayAdministrateStoreChains(): bool
    {
        return $this->session->mayRole(Role::ORGA) || $this->currentUserUnits->isAdminFor(RegionIDs::STORE_CHAIN_GROUP);
    }

    public function mayAdministrateStoreChain($chainId): bool
    {
        return $this->mayAdministrateStoreChains() || $this->gateway->isUserKeyAccountManager($chainId, $this->session->id());
    }

    public function maySeeChainList(): bool
    {
        return $this->session->mayRole(Role::STORE_MANAGER) ||
            ($this->session->mayRole(Role::FOODSAVER) &&
                $this->regionGateway->hasMember($this->session->id(), RegionIDs::STORE_CHAIN_GROUP)) ||
            $this->storeGateway->isStoreTeamMemberOfStoreChainStore($this->session->id());
    }

    public function mayCreateChain(): bool
    {
        return $this->mayAdministrateStoreChains();
    }

    public function mayEditChain($chainId): bool
    {
        return $this->mayAdministrateStoreChain($chainId);
    }

    public function mayEditKams($chainId): bool
    {
        return $this->mayAdministrateStoreChains();
    }

    public function maySeeChainStores($chainId): bool
    {
        if ($this->session->mayRole(Role::FOODSAVER) &&
            $this->regionGateway->hasMember($this->session->id(), RegionIDs::STORE_CHAIN_GROUP)) {
            return true;
        }

        return $this->mayAdministrateStoreChain($chainId);
    }

    public function maySeeChainDetails($chainId = null): bool
    {
        if ($this->session->mayRole(Role::FOODSAVER) &&
            $this->regionGateway->hasMember($this->session->id(), RegionIDs::STORE_CHAIN_GROUP)) {
            return true;
        }

        if (empty($chainId)) {
            return $this->mayAdministrateStoreChains();
        }

        return $this->mayAdministrateStoreChain($chainId);
    }
}
