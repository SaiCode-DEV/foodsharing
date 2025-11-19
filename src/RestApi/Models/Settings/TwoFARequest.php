<?php

namespace Foodsharing\RestApi\Models\Settings;

class TwoFARequest
{
    public string $code;

    public string $password;

    public bool $enable;

    public function __construct(
        string $code,
        string $password,
        bool $enable
    ) {
        $this->code = $code;
        $this->password = $password;
        $this->enable = $enable;
    }
}
