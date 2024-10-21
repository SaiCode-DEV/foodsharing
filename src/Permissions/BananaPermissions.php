<?php

declare(strict_types=1);

namespace Foodsharing\Permissions;

use Foodsharing\Lib\Session;
use Foodsharing\Modules\Banana\BananaGateway;
use Foodsharing\Modules\Core\DBConstants\Region\RegionIDs;
use Foodsharing\Modules\Unit\CurrentUserUnitsInterface;

final class BananaPermissions
{
    public function __construct(
        private readonly Session $session,
        private readonly CurrentUserUnitsInterface $currentUserUnits,
        private readonly BananaGateway $bananaGateway,
    ) {
    }

    public function mayGiveBanana($recipientId): bool
    {
        if ($this->session->id() === $recipientId) {
            return false;
        }
        if ($this->bananaGateway->hasGivenBanana($recipientId, $this->session->id())) {
            return false;
        }

        return true;
    }

    public function mayDeleteBanana(int $recipientId, int $senderId): bool
    {
        return $this->session->id() === $recipientId ||
            $this->session->id() === $senderId ||
            $this->mayDeleteBananas();
    }

    public function mayDeleteBananas(): bool
    {
        return $this->currentUserUnits->isAdminFor(RegionIDs::IT_SUPPORT_GROUP);
    }
}
