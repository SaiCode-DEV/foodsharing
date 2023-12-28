<?php

namespace Foodsharing\Modules\Buddy;

use Foodsharing\Lib\Session;
use Foodsharing\Modules\Bell\BellGateway;
use Foodsharing\Modules\Bell\DTO\Bell;
use Foodsharing\Modules\Core\DBConstants\Bell\BellType;

class BuddyTransactions
{
    private BuddyGateway $buddyGateway;
    private BellGateway $bellGateway;
    private Session $session;

    public function __construct(
        BuddyGateway $buddyGateway,
        BellGateway $bellGateway,
        Session $session
    ) {
        $this->buddyGateway = $buddyGateway;
        $this->bellGateway = $bellGateway;
        $this->session = $session;
    }

    /**
     * Updates the buddy status and deletes open bell notifications.
     *
     * @param int $userId ID of another user
     */
    public function acceptBuddyRequest(int $userId): void
    {
        $this->buddyGateway->confirmBuddy($userId, $this->session->id());

        $this->deleteBuddyRequestBells($userId);

        $buddyIds = $this->session->get('buddy-ids') ?: [];

        $buddyIds[$userId] = $userId;
        $this->session->set('buddy-ids', $buddyIds);
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
}
