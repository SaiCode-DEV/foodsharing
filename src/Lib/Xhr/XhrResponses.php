<?php

namespace Foodsharing\Lib\Xhr;

class XhrResponses
{
    final public const PERMISSION_DENIED = 'permission_denied';

    public function fail_permissions()
    {
        return self::PERMISSION_DENIED;
    }

    public function fail_generic()
    {
        return ['status' => 0];
    }
}
