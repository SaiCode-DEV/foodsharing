<?php

namespace Foodsharing\Modules\Report\DTO;

use Foodsharing\Modules\Foodsaver\Profile;

class ProfileWithMail extends Profile
{
    public ?string $mail;
    public ?string $lastName;

    public function __construct(array $data, string $prefix = '')
    {
        parent::__construct($data, $prefix);
        $this->mail = $data[$prefix . 'email'] ?? null;
        $this->lastName = $data[$prefix . 'last_name'] ?? null;
    }
}
