<?php

declare(strict_types=1);

namespace Foodsharing\RestApi\Models\EmailBlocklist;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Model for creating or updating an email blocklist entry.
 */
#[OA\Schema]
class EmailBlocklistEntryModel
{
    #[Assert\NotBlank(message: 'email is required')]
    #[Assert\Length(max: 255)]
    #[OA\Property(description: 'Email pattern to block (can include wildcards)', example: '*@spam-domain.com')]
    public string $email;

    #[Assert\Length(max: 500)]
    #[OA\Property(description: 'Reason for blocking this email pattern', example: 'Spam domain', nullable: true)]
    public ?string $reason = null;

    #[OA\Property(description: 'Whether this blocklist entry is active', example: true)]
    public bool $isActive = true;
}
