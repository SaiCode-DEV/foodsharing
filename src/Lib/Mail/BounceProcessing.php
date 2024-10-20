<?php

namespace Foodsharing\Lib\Mail;

use BounceMailHandler\BounceMailHandler;
use Foodsharing\Modules\Mails\MailsGateway;

class BounceProcessing
{
    private int $numBounces = 0;

    public function __construct(
        private readonly BounceMailHandler $bounceMailHandler,
        private readonly MailsGateway $mailsGateway
    ) {
    }

    public function process(): void
    {
        $this->bounceMailHandler->actionFunction = $this->handleBounce(...);
        $this->bounceMailHandler->openMailbox();
        $this->bounceMailHandler->processMailbox();
        /* catch errors/notices that would otherwise fall through */
        imap_errors();
    }

    /**
     * @noinspection PhpUnusedParameterInspection this is a callback, see {@link BounceMailHandler::$actionFunction}
     */
    public function handleBounce($msgnum, $bounceType, $email, $subject, $xheader, $remove, $ruleNo = false, $ruleCat = false, $totalFetched = 0, $body = '', $headerFull = '', $bodyFull = ''): void
    {
        if ($bounceType !== false) {
            $this->mailsGateway->addBounceForMail($email, $ruleCat, new \DateTime());
            ++$this->numBounces;
        }
    }
}
