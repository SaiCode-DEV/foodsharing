<?php

namespace Foodsharing\RestApi\Models\Legal;

use Symfony\Component\Validator\Constraints as Assert;

class LegalAcknowledge
{
    #[Assert\NotNull(message: 'Policy acceptance is required')]
    #[Assert\Type('boolean')]
    public bool $policy = false;

    #[Assert\Type('boolean')]
    public ?bool $notice = null;
}
