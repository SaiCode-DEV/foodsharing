<?php

declare(strict_types=1);

namespace Foodsharing\RestApi\Models\Donation;

use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;

class DonationInformation
{
    #[OA\Property(description: 'Project ID', example: 1)]
    public int $projectId;

    #[OA\Property(ref: new Model(type: DonationProjectStatus::class), description: 'Donation Project Status')]
    public DonationProjectStatus $donationProjectStatus;

    public function __construct(int $projectId, DonationProjectStatus $donationProjectStatus)
    {
        $this->projectId = $projectId;
        $this->donationProjectStatus = $donationProjectStatus;
    }
}
