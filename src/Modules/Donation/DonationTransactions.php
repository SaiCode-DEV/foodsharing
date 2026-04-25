<?php

namespace Foodsharing\Modules\Donation;

use Carbon\Carbon;
use Foodsharing\Modules\Donation\Query\TwingleDonationDataQuery;
use Foodsharing\RestApi\Models\Donation\DonationInformation;
use Foodsharing\RestApi\Models\Donation\DonationProjectStatus;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class DonationTransactions
{
    private const int TWINGLE_CACHE_INTERVAL = 10 * 60; // in seconds

    public function __construct(
        private readonly CacheInterface $cache,
        private readonly TwingleDonationDataQuery $twingleDonationDataQuery,
        private readonly DonationGateway $donationGateway,
    ) {
    }

    /**
     * Returns the status of each of the Twingle projects. This either fetches the information from Twingle or uses
     * the cached data.
     *
     * @return DonationInformation[]
     */
    public function getDonationInformation(): array
    {
        // the configuration from the database contains the necessary URLs
        $donationData = $this->donationGateway->getDonationData();

        // fetch the information from Twingle and cache it
        return $this->cache->get('foodsharingDonationProjectStatus', function (ItemInterface $cacheItem) use ($donationData) {
            $cacheItem->expiresAfter(self::TWINGLE_CACHE_INTERVAL);

            $donationProjectIds = [
                $donationData->friendshipCircleId,
                $donationData->campaignId,
                $donationData->oneTimeDonationId,
            ];
            $allDonationInformations = [];
            foreach ($donationProjectIds as $projectId) {
                $twingleDonationData = $this->twingleDonationDataQuery->getProjectStatus($projectId);
                $allDonationInformations[] = new DonationInformation(
                    $projectId,
                    $this->convertTwingleDonationData($twingleDonationData)
                );
            }

            return $allDonationInformations;
        });
    }

    private function convertTwingleDonationData(array $twingleDonationDataAsArray): DonationProjectStatus
    {
        return new DonationProjectStatus(
            donators: $twingleDonationDataAsArray['donators'],
            goalInEuros: $twingleDonationDataAsArray['target'],
            isGoalReached: $twingleDonationDataAsArray['amount'] >= $twingleDonationDataAsArray['target'],
            percentOfGoalReached: $twingleDonationDataAsArray['percentage'],
            receivedDonationsInEuros: $twingleDonationDataAsArray['amount'],
            updatedAt: Carbon::now(),
        );
    }
}
