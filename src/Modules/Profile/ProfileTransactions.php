<?php

namespace Foodsharing\Modules\Profile;

use Foodsharing\Modules\Foodsaver\FoodsaverGateway;
use Foodsharing\Modules\Mails\MailsGateway;

class ProfileTransactions
{
    private readonly FoodsaverGateway $foodsaverGateway;
    private readonly MailsGateway $mailsGateway;

    public function __construct(
        FoodsaverGateway $foodsaverGateway,
        MailsGateway $mailsGateway
    ) {
        $this->foodsaverGateway = $foodsaverGateway;
        $this->mailsGateway = $mailsGateway;
    }

    /**
     * Removes the user's personal (login) email address from the bounce list.
     */
    public function removeUserFromBounceList(int $userId): void
    {
        $address = $this->foodsaverGateway->getEmailAddress($userId);
        $this->mailsGateway->removeBounceForMail($address);
    }
}
