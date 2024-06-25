<?php

declare(strict_types=1);

namespace Foodsharing\RestApi;

use Carbon\Carbon;
use Foodsharing\Modules\Donation\Query\TwingleDonationDataQuery;
use Foodsharing\RestApi\Models\Donation\DonationGoalInformation;
use FOS\RestBundle\Controller\AbstractFOSRestController;
use FOS\RestBundle\Controller\Annotations as Rest;
use Nelmio\ApiDocBundle\Annotation\Model;
use OpenApi\Attributes\Response;
use OpenApi\Attributes\Tag;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

final class DonationGoalRestController extends AbstractFOSRestController
{
    private const TEN_MINUTES_IN_SECONDS = 600;

    public function __construct(
        private readonly CacheInterface $cache,
        private readonly TwingleDonationDataQuery $twingleDonationDataQuery,
    ) {
    }

    /**
     * Returns cached information of foodsharing donation-goal via third service provider twingle.
     */
    #[Tag('donation')]
    #[Rest\Get(path: 'donation-goal')]
    #[Response(response: HttpResponse::HTTP_OK, description: 'Successful', content: new Model(type: DonationGoalInformation::class))]
    public function getInformationAction(): JsonResponse
    {
        $donationGoalInformation = $this->cache->get('foodsharingDonationGoalInformation', function (ItemInterface $cacheItem) {
            $cacheItem->expiresAfter(self::TEN_MINUTES_IN_SECONDS);

            $twingleDonationData = $this->twingleDonationDataQuery->execute();

            return $this->convertTwingleDonationData($twingleDonationData);
        });

        return $this->json(
            $donationGoalInformation,
            HttpResponse::HTTP_OK,
        );
    }

    private function convertTwingleDonationData(array $twingleDonationDataAsArray): DonationGoalInformation
    {
        return new DonationGoalInformation(
            donators: $twingleDonationDataAsArray['donators'],
            goalInEuros: $twingleDonationDataAsArray['target'],
            isGoalReached: $twingleDonationDataAsArray['amount'] >= $twingleDonationDataAsArray['target'],
            percentOfGoalReached: $twingleDonationDataAsArray['percentage'],
            receivedDonationsInEuros: $twingleDonationDataAsArray['amount'],
            updatedAt: Carbon::now()->toIso8601String(),
        );
    }
}
