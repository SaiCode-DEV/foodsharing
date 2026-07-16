<?php

declare(strict_types=1);

namespace Foodsharing\RestApi\Models\Donation;

use Foodsharing\Modules\Donation\DonationData;
use OpenApi\Attributes as OA;

/**
 * Extension of the DonationData class by all fields that are returned in a response by the API, but not required by
 * a request to the Patch endpoint.
 */
class DonationDataResponse extends DonationData
{
    #[OA\Property(description: 'Campaign ID', example: 12345)]
    public ?array $donationInformation = null;
}
