<?php

namespace Foodsharing\Modules\Store\DTO;

class StorePermissions
{
    public function __construct(
        public int $storeId,
        public bool $isCoordinator,
        public bool $isAmbassador,
        public bool $isOrgaUser,
        public bool $isJumper,
        public bool $isManager,
        public bool $isKam,
        public bool $maySeePickup,
        public bool $mayEditStore,
        public bool $mayLeaveStoreTeam,
        public bool $maySeePickupHistory,
        public bool $maySeeStoreLog,
        public bool $maySeePickups,
        public bool $mayDeleteStore,
        public ?int $teamConversationId,
        public ?int $jumperConversationId,
    ) {
    }
}
