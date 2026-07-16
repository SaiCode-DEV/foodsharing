<?php

declare(strict_types=1);

namespace Foodsharing\RestApi\Models\Donation;

use OpenApi\Attributes as OA;

class DonationInformations
{
    #[OA\Property(
        description: 'Project ID',
        example: 1,
    )]
    public int $projectId = 0;

    #[OA\Property(
        description: 'Donation Project Status',
        ref: DonationProjectStatus::class,
    )]
    public ?DonationProjectStatus $donationProjectStatus = null;

    public function __construct(int $projectId = 0, ?DonationProjectStatus $donationProjectStatus = null)
    {
        $this->projectId = $projectId;
        $this->donationProjectStatus = $donationProjectStatus;
    }
}
