<?php

namespace Foodsharing\Modules\Report\DTO;

use Foodsharing\Modules\Foodsaver\Profile;

class ProfileWithMail extends Profile
{
    public ?string $mail;
    public ?string $lastName;

    public function __construct(int $id, string $name, ?string $avatar, ?bool $isSleeping, ?string $mail, ?string $lastName)
    {
        parent::__construct($id, $name, $avatar, $isSleeping);
        $this->mail = $mail;
        $this->lastName = $lastName;
    }
}
