<?php

declare(strict_types=1);

namespace Foodsharing\Modules\Register\DTO;

use OpenApi\Attributes as OA;

/**
 * Class that represents the result of registering a new user.
 */
class RegisterResult
{
    #[OA\Property(description: 'Whether an error occurred while trying to subscribe to the newsletter')]
    public bool $hasNewsletterSubscriptionFailed = false;
}
