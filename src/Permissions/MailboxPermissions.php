<?php

namespace Foodsharing\Permissions;

use Foodsharing\Lib\Session;
use Foodsharing\Modules\Core\DBConstants\Foodsaver\Role;
use Foodsharing\Modules\Mailbox\MailboxGateway;
use Foodsharing\Modules\Unit\CurrentUserUnitsInterface;

class MailboxPermissions
{
    private readonly Session $session;
    private readonly MailboxGateway $mailboxGateway;

    public function __construct(MailboxGateway $mailboxGateway, Session $session, protected readonly CurrentUserUnitsInterface $currentUserUnits)
    {
        $this->mailboxGateway = $mailboxGateway;
        $this->session = $session;
    }

    public function mayMessage(int $mid): bool
    {
        if (!$this->mayHaveMailbox()) {
            return false;
        }

        if ($mailbox_id = $this->mailboxGateway->getMailboxId($mid)) {
            return $this->mayMailbox($mailbox_id);
        }

        return false;
    }

    public function mayMailbox(int $mailboxId): bool
    {
        $mailboxes = $this->mailboxGateway->getBoxes($this->currentUserUnits->isAmbassador(), $this->session->id());

        foreach ($mailboxes as $mailbox) {
            if ($mailbox->id == $mailboxId) {
                return true;
            }
        }

        return false;
    }

    public function mayHaveMailbox(): bool
    {
        return $this->session->mayRole(Role::STORE_MANAGER);
    }
}
