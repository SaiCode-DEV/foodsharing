<?php

declare(strict_types=1);

namespace Foodsharing\RestApi;

use Carbon\Carbon;
use Foodsharing\Lib\Session;
use Foodsharing\Modules\Donation\Query\TwingleDonationDataQuery;
use Foodsharing\RestApi\Models\Donation\DonationGoalInformation;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

#[OA\Tag('donation')]
final class DonationGoalRestController extends AbstractFoodsharingRestController
{
    private const int TEN_MINUTES_IN_SECONDS = 600;

    public function __construct(
        private readonly CacheInterface $cache,
        private readonly TwingleDonationDataQuery $twingleDonationDataQuery,
        protected Session $session,
    ) {
        parent::__construct($session);
    }

    #[OA\Get(summary: 'Returns cached information of foodsharing donation-goal via third service provider twingle.')]
    #[Route('donation-goal', methods: ['GET'])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success', content: new Model(type: DonationGoalInformation::class))]
    #[OA\Response(response: Response::HTTP_SERVICE_UNAVAILABLE, description: 'Currently unavailable because there is no donation campaign')]
    public function getInformation(bool $isUnavailable = true): Response
    {
        if ($isUnavailable) {
            throw new HttpException(Response::HTTP_SERVICE_UNAVAILABLE, 'This endpoint is currently disabled.');
        }

        $donationGoalInformation = $this->cache->get('foodsharingDonationGoalInformation', function (ItemInterface $cacheItem) {
            $cacheItem->expiresAfter(self::TEN_MINUTES_IN_SECONDS);

            $twingleDonationData = $this->twingleDonationDataQuery->execute();

            return $this->convertTwingleDonationData($twingleDonationData);
        });

        return $this->respondOK($donationGoalInformation);
    }

    private function convertTwingleDonationData(array $twingleDonationDataAsArray): DonationGoalInformation
    {
        return new DonationGoalInformation(
            donators: $twingleDonationDataAsArray['donators'],
            goalInEuros: $twingleDonationDataAsArray['target'],
            isGoalReached: $twingleDonationDataAsArray['amount'] >= $twingleDonationDataAsArray['target'],
            percentOfGoalReached: $twingleDonationDataAsArray['percentage'],
            receivedDonationsInEuros: $twingleDonationDataAsArray['amount'],
            updatedAt: Carbon::now(),
        );
    }
}
