<?php

declare(strict_types=1);

namespace Foodsharing\RestApi\Models\Donation;

use OpenApi\Attributes as OA;

class DonationInformation
{
    #[OA\Property(description: 'Project ID', example: 1)]
    public int $projectId;

    #[OA\Property(ref: DonationProjectStatus::class, description: 'Donation Project Status')]
    public DonationProjectStatus $donationProjectStatus;

    public function __construct(int $projectId, DonationProjectStatus $donationProjectStatus)
    {
        $this->projectId = $projectId;
        $this->donationProjectStatus = $donationProjectStatus;
    }
}
