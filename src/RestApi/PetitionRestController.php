<?php

declare(strict_types=1);

namespace Foodsharing\RestApi;

use Foodsharing\Modules\Development\FeatureToggles\DependencyInjection\FeatureToggleChecker;
use Foodsharing\Modules\Development\FeatureToggles\Enums\FeatureToggleDefinitions;
use Foodsharing\Modules\Petition\Query\BundestagPetitionDataQuery;
use FOS\RestBundle\Controller\Annotations as Rest;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

#[OA\Tag('petition')]
final class PetitionRestController extends AbstractFoodsharingRestController
{
    private const TEN_MINUTES_IN_SECONDS = 600;

    public function __construct(
        private readonly CacheInterface $cache,
        private readonly BundestagPetitionDataQuery $bundestagPetitionDataQuery,
        private readonly FeatureToggleChecker $featureToggleChecker,
    ) {
    }

    #[OA\Get(summary: 'Returns possibly cached information of foodsharing petition at bundestag.')]
    #[Rest\Get(path: 'petition-data')]
    #[OA\Response(response: Response::HTTP_OK, description: 'Successful')]
    public function getSignaturesCount()
    {
        if (!$this->featureToggleChecker->isFeatureToggleActive(FeatureToggleDefinitions::PETITION_BANNER->value)) {
            return $this->respondOK();
        }

        $petitionData = $this->cache->get('foodsharingPetitionData', function (ItemInterface $cacheItem) {
            $cacheItem->expiresAfter(self::TEN_MINUTES_IN_SECONDS);

            $petitionData = $this->bundestagPetitionDataQuery->execute();

            return $petitionData;
        });

        return $this->respondOK($petitionData);
    }
}
