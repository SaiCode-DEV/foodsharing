<?php

declare(strict_types=1);

namespace Foodsharing\RestApi\Models\Donation;

use DateTime;
use OpenApi\Attributes as OA;

class DonationGoalInformation
{
    #[OA\Property(
        description: 'amount of donators',
        example: 42,
    )]
    public readonly int $donators;

    #[OA\Property(
        description: 'amount of recevied donations in euros',
        example: 1337,
    )]
    public readonly float $receivedDonationsInEuros;

    #[OA\Property(
        description: 'amount of donation-goal in euros',
        example: 2000,
    )]
    public readonly int $goalInEuros;

    #[OA\Property(
        description: 'procentual amount of donation-goal reached',
        example: 66.85,
    )]
    public readonly float $percentOfGoalReached;

    #[OA\Property(
        description: 'is donation-goal reached',
        example: false,
    )]
    public readonly bool $isGoalReached;

    public readonly DateTime $updatedAt;

    public function __construct(
        int $donators,
        int $goalInEuros,
        bool $isGoalReached,
        float $percentOfGoalReached,
        float $receivedDonationsInEuros,
        DateTime $updatedAt,
    ) {
        $this->donators = $donators;
        $this->goalInEuros = $goalInEuros;
        $this->isGoalReached = $isGoalReached;
        $this->percentOfGoalReached = $percentOfGoalReached;
        $this->receivedDonationsInEuros = $receivedDonationsInEuros;
        $this->updatedAt = $updatedAt;
    }
}
