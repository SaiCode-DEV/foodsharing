<?php

namespace Foodsharing\Modules\Mails;

class IncomingEmailStatistics
{
    public int $unknownRecipient = 0;
    public int $delivered = 0;
    public int $hasAttachment = 0;
}
