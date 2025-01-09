<?php

namespace Foodsharing\Modules\Buddy;

use Foodsharing\Lib\Session;
use Foodsharing\Modules\Bell\BellGateway;
use Foodsharing\Modules\Bell\DTO\Bell;
use Foodsharing\Modules\Core\DBConstants\Bell\BellType;

class BuddyTransactions
{
    private const string SESSION_BUDDY_IDS_IDENTIFIER = 'buddy-ids';

    public function __construct(
        private readonly BuddyGateway $buddyGateway,
        private readonly BellGateway $bellGateway,
        private readonly Session $session
    ) {
    }

    /**
     * Updates the buddy status (in DB and cache) and deletes open bell notifications.
     *
     * @param int $userId ID of another user
     */
    public function acceptBuddyRequest(int $userId): void
    {
        $fsId = $this->session->id();
        $this->buddyGateway->confirmBuddy($userId, $fsId);

        $this->deleteBuddyRequestBells($userId);

        $this->reloadMyBuddyListSessionCache();
    }

    /**
     * Sends a buddy request and creates a bell notification.
     *
     * @param int $userId ID of another user
     */
    public function sendBuddyRequest(int $userId): void
    {
        $this->buddyGateway->buddyRequest($userId, $this->session->id());
        $this->bellGateway->addBell($userId, Bell::create(
            'buddy_request_title',
            'buddy_request',
            $this->session->user('photo') ?? '',
            ['href' => '/profile/' . (int)$this->session->id()],
            ['name' => $this->session->user('name')],
            BellType::createIdentifier(BellType::BUDDY_REQUEST, $this->session->id(), $userId)
        ));
    }

    /**
     * Removes a buddy request.
     *
     * @param int $userId ID of another user
     */
    public function removeBuddyRequest(int $userId): void
    {
        $this->buddyGateway->removeRequest($this->session->id(), $userId);
        if ($this->buddyGateway->hasSentBuddyRequest($userId, $this->session->id())) {
            $this->buddyGateway->unconfirmBuddy($this->session->id(), $userId);
        }
        $this->deleteBuddyRequestBells($userId);
    }

    private function deleteBuddyRequestBells(int $userId): void
    {
        $this->bellGateway->delBellsByIdentifier(BellType::createIdentifier(BellType::BUDDY_REQUEST, $this->session->id(), $userId));
        $this->bellGateway->delBellsByIdentifier(BellType::createIdentifier(BellType::BUDDY_REQUEST, $userId, $this->session->id()));
    }

    public function listBuddiesIds(): array
    {
        $buddies = $this->session->get(self::SESSION_BUDDY_IDS_IDENTIFIER);
        if ($buddies === false) {
            $this->reloadMyBuddyListSessionCache();
            $buddies = $this->session->get(self::SESSION_BUDDY_IDS_IDENTIFIER);
        }

        return $buddies ?: [];
    }

    private function reloadMyBuddyListSessionCache()
    {
        $buddies = $this->buddyGateway->listBuddyIds($this->session->id());
        $this->session->set(self::SESSION_BUDDY_IDS_IDENTIFIER, $buddies);
    }
}
