<?php

declare(strict_types=1);

namespace Foodsharing\Modules\Login\DTO;

use OpenApi\Attributes as OA;

class EmailBlocklistEntry
{
    public function __construct(
        #[OA\Property(description: 'Unique ID of the blacklist entry', example: 123)]
        public int $id,

        #[OA\Property(description: 'Blacklisted email address', example: 'blocked@example.com')]
        public string $email,

        #[OA\Property(description: 'Reason why the email address was blacklisted', example: 'Spam', nullable: true)]
        public ?string $reason,

        #[OA\Property(description: 'Whether the blacklist entry is currently active', example: true)]
        public bool $isActive,

        #[OA\Property(description: 'Creation date of the blacklist entry', example: '2026-08-09 07:30:00')]
        public string $createdAt,

        #[OA\Property(description: 'ID of the user who created the entry', example: 42, nullable: true)]
        public ?int $createdBy,

        #[OA\Property(description: 'Last update date of the blacklist entry', example: '2026-08-09 08:00:00', nullable: true)]
        public ?string $updatedAt,

        #[OA\Property(description: 'ID of the user who last updated the entry', example: 42, nullable: true)]
        public ?int $updatedBy,
    ) {
    }
}
