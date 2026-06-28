<?php

declare(strict_types=1);

namespace Foodsharing\RestApi\Models\EmailBlocklist;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Model for updating an email blocklist entry (partial update).
 */
#[OA\Schema]
class EmailBlocklistEntryPatchModel
{
    #[Assert\Length(max: 255)]
    #[OA\Property(description: 'Email pattern to block (can include wildcards)', example: '*@spam-domain.com', nullable: true)]
    public ?string $email = null;

    #[Assert\Length(max: 500)]
    #[OA\Property(description: 'Reason for blocking this email pattern', example: 'Spam domain', nullable: true)]
    public ?string $reason = null;

    #[OA\Property(description: 'Whether this blocklist entry is active', example: true, nullable: true)]
    public ?bool $isActive = null;
}
