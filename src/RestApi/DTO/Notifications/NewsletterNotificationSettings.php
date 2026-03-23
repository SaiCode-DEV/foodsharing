<?php

declare(strict_types=1);

namespace Foodsharing\RestApi\DTO\Notifications;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

class NewsletterNotificationSettings
{
    #[OA\Property(description: 'Whether the user is subscribed to the newsletter.')]
    #[Assert\NotNull]
    public bool $isNewsletterSubscribed;
}
