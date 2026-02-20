<?php

declare(strict_types=1);

namespace Foodsharing\RestApi\DTO\Notifications;

use Foodsharing\Modules\Store\DTO\CommonLabel;
use OpenApi\Attributes as OA;

class NotificationSettingWithRegion extends NotificationSetting
{
    public function __construct(
        int $id, string $name, bool $bell, bool $email,

        #[OA\Property(description: 'The region this settings entry is associated with')]
        public CommonLabel $region,
    ) {
        parent::__construct($id, $name, $bell, $email);
    }
}
