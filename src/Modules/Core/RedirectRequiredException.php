<?php

namespace Foodsharing\Modules\Core;

use RuntimeException;

/**
 * Can be thrown by any controller to signal that the client should be redirected to another page. The
 * ExceptionEventSubscriber catches it and returns a 302 response to the client.
 */
class RedirectRequiredException extends RuntimeException
{
    public function __construct(private readonly string $targetUrl = '/')
    {
        parent::__construct();
    }

    public function getTargetUrl(): string
    {
        return $this->targetUrl;
    }
}
