<?php

declare(strict_types=1);

namespace Foodsharing\RestApi\DTO\Notifications;

use Foodsharing\Modules\Store\DTO\CommonLabel;
use OpenApi\Attributes as OA;

class NotificationSetting extends CommonLabel
{
    public function __construct(
        int $id, string $name,

        #[OA\Property(description: 'Whether a notification via bell is activated. May be null, meaning the default value is used')]
        public ?bool $bell,

        #[OA\Property(description: 'Whether a notification via email is activated')]
        public bool $email
    ) {
        parent::__construct($id, $name);
    }
}
