<?php

namespace Foodsharing\Modules\Donation;

use Carbon\Carbon;
use Foodsharing\Modules\Donation\Query\TwingleDonationDataQuery;
use Foodsharing\RestApi\Models\Donation\DonationDataResponse;
use Foodsharing\RestApi\Models\Donation\DonationInformation;
use Foodsharing\RestApi\Models\Donation\DonationProjectStatus;
use Foodsharing\RestApi\Models\Donation\ProjectData;
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
     * Returns the donation configuration and the status of each Twingle project.
     *
     * Twingle responses are cached, configuration values are read directly from DB.
     */
    public function getDonationInformation(): DonationDataResponse
    {
        // the configuration from the database contains the necessary URLs
        $donationData = $this->donationGateway->getDonationData();

        // fetch the information from Twingle and cache it
        $donationData->donationInformation = $this->cache->get('foodsharingDonationProjectStatus', function (ItemInterface $cacheItem) use ($donationData) {
            $cacheItem->expiresAfter(self::TWINGLE_CACHE_INTERVAL);

            // A project that is not configured has the id 0. Twingle cannot answer for it, and one
            // failing project would take the status of all the others down with it.
            $donationProjectIds = array_filter([
                $donationData->friendshipCircleId,
                $donationData->campaignId,
                $donationData->oneTimeDonationId,
            ], fn (int $projectId): bool => $projectId > 0);
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

        return $donationData;
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

    /**
     * Returns the donation configuration and the status of each Twingle project.
     *
     * Twingle responses are cached, configuration values are read directly from DB.
     *
     * @return ProjectData[]
     */
    public function getProjects(): array
    {
        // fetch the information from Twingle and cache it
        return $this->cache->get('foodsharingDonationProjects', function (ItemInterface $cacheItem) {
            $cacheItem->expiresAfter(self::TWINGLE_CACHE_INTERVAL);

            $twingleProjectData = $this->twingleDonationDataQuery->getProjects();

            return array_map(fn ($project) => new ProjectData(
                id: $project['id'],
                name: $project['name'],
            ), $twingleProjectData);
        });
    }
}
