<?php

namespace Foodsharing\RestApi\Models\Settings;

class PasswordChangeRequest
{
    public string $oldPassword;

    public string $newPassword;

    public ?string $totpCode = null;
}
