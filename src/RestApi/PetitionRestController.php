<?php

declare(strict_types=1);

namespace Foodsharing\RestApi;

use Foodsharing\Lib\Session;
use Foodsharing\Modules\Development\FeatureToggles\DependencyInjection\FeatureToggleChecker;
use Foodsharing\Modules\Development\FeatureToggles\Enums\FeatureToggleDefinitions;
use Foodsharing\Modules\Petition\Query\BundestagPetitionDataQuery;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

#[OA\Tag('petition')]
final class PetitionRestController extends AbstractFoodsharingRestController
{
    private const int TEN_MINUTES_IN_SECONDS = 600;

    public function __construct(
        private readonly CacheInterface $cache,
        private readonly BundestagPetitionDataQuery $bundestagPetitionDataQuery,
        private readonly FeatureToggleChecker $featureToggleChecker,
        protected Session $session,
    ) {
        parent::__construct($session);
    }

    #[OA\Get(summary: 'Returns possibly cached information of foodsharing petition at bundestag.')]
    #[Route('petition', methods: ['GET'])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success', content: new OA\JsonContent(type: 'object', properties: [
        new OA\Property(property: 'href', type: 'string', description: 'Link to the petition page.'),
        new OA\Property(property: 'signatures', type: 'integer', description: 'Number of online signatures.'),
        new OA\Property(property: 'daysLeft', type: 'integer', description: 'Number of days left to sign the petition.'),
    ]))]
    #[OA\Response(response: Response::HTTP_SERVICE_UNAVAILABLE, description: 'No active petition.')]
    public function getSignaturesCount()
    {
        if (!$this->featureToggleChecker->isFeatureToggleActive(FeatureToggleDefinitions::PETITION_BANNER->value)) {
            return throw new ServiceUnavailableHttpException(null, 'Currently no petition active.');
        }

        $petitionData = $this->cache->get('foodsharingPetitionData', function (ItemInterface $cacheItem) {
            $cacheItem->expiresAfter(self::TEN_MINUTES_IN_SECONDS);

            $petitionData = $this->bundestagPetitionDataQuery->execute();

            return $petitionData;
        });

        return $this->respondOK($petitionData);
    }
}
