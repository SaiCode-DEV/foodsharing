<?php

declare(strict_types=1);

namespace Foodsharing\RestApi;

use Foodsharing\Lib\Session;
use Foodsharing\Modules\Core\DBConstants\Unit\UnitType;
use Foodsharing\Modules\Region\DTO\RegionPickupStatistics;
use Foodsharing\Modules\Region\RegionGateway;
use Foodsharing\Modules\Region\RegionTransactions;
use Foodsharing\Modules\Statistics\DTO\StatisticsAgeBand;
use Foodsharing\Modules\Statistics\DTO\StatisticsGender;
use Foodsharing\Modules\Statistics\StatisticsGateway;
use Foodsharing\Permissions\RegionPermissions;
use Foodsharing\RestApi\Models\Statistic\GeneralStatistic;
use Foodsharing\RestApi\Models\Statistic\PickupModel;
use Foodsharing\RestApi\Models\Statistic\StatisticModel;
use Nelmio\ApiDocBundle\Annotation\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

#[OA\Tag(name: 'statistics')]
class StatisticsRestController extends AbstractFoodsharingRestController
{
    private const int OVERALL_STATISTICS_CACHE_DURATION = 12 * 60 * 60;

    public function __construct(
        private readonly StatisticsGateway $statisticsGateway,
        private readonly RegionGateway $regionGateway,
        private readonly RegionPermissions $regionPermissions,
        private readonly RegionTransactions $regionTransactions,
        private readonly CacheInterface $cache,
        protected Session $session,
    ) {
        parent::__construct($session);
    }

    #[OA\Get(
        description: 'If home region is set only the home region of foodsavers from this regionId are considered.',
        summary: 'Returns the gender distribution from a region'
    )]
    #[Route('regions/{regionId}/statistics/gender', requirements: ['regionId' => Requirement::POSITIVE_INT], methods: ['GET'])]
    #[OA\Response(
        response: Response::HTTP_OK,
        description: 'Successful',
        content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: new Model(type: StatisticsGender::class)))
    )]
    #[OA\Response(response: Response::HTTP_NOT_FOUND, description: 'Region does not exist')]
    public function listRegionGenderStatistic(int $regionId, #[MapQueryParameter] bool $onlyHomeRegion = false): Response
    {
        $this->assertLoggedIn();

        if (!$this->isRegion($regionId)) {
            throw new NotFoundHttpException("Region with id {$regionId} not found");
        }

        $result = $onlyHomeRegion
            ? $this->statisticsGateway->genderCountHomeRegion($regionId)
            : $this->statisticsGateway->genderCountRegion($regionId);

        return $this->respondOK($result);
    }

    #[OA\Get(
        description: 'If home region is set only the home region of foodsavers from this regionId are considered.',
        summary: 'Returns the age band distribution from a region',
    )]
    #[Route('regions/{regionId}/statistics/age-band', requirements: ['regionId' => Requirement::POSITIVE_INT], methods: ['GET'])]
    #[OA\Response(
        response: Response::HTTP_OK,
        description: 'Successful',
        content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: new Model(type: StatisticsAgeBand::class)))
    )]
    #[OA\Response(response: Response::HTTP_NOT_FOUND, description: 'Region does not exist')]
    public function listRegionAgeBandStatistic(int $regionId, #[MapQueryParameter] bool $onlyHomeRegion = false): Response
    {
        $this->assertLoggedIn();

        if (!$this->isRegion($regionId)) {
            throw new NotFoundHttpException("Region with id {$regionId} not found");
        }

        $result = $onlyHomeRegion
            ? $this->statisticsGateway->ageBandHomeDistrict($regionId)
            : $this->statisticsGateway->ageBandDistrict($regionId);

        return $this->respondOK($result);
    }

    #[OA\Get(summary: 'Returns the pickup statistics of a region')]
    #[Route('regions/{regionId}/statistics/pickups', requirements: ['regionId' => Requirement::POSITIVE_INT], methods: ['GET'])]
    #[OA\Response(
        response: Response::HTTP_OK,
        description: 'Successful',
        content: new OA\JsonContent(type: RegionPickupStatistics::class)
    )]
    #[OA\Response(response: Response::HTTP_NOT_FOUND, description: 'Region does not exist')]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Pick-up statistics are currently not available for countries')]
    public function listRegionPickupsStatistics(int $regionId): Response
    {
        $this->assertLoggedIn();

        $region = $this->regionGateway->getRegion($regionId);
        if (empty($region)) {
            throw new NotFoundHttpException("Region with id {$regionId} not found");
        }
        if (in_array($region['type'], [UnitType::COUNTRY, UnitType::CONTINENT]) && !$this->regionPermissions->mayAccessStatisticCountry()) {
            throw new AccessDeniedHttpException();
        }

        $result = $this->regionTransactions->getRegionPickupStatistics($regionId);

        return $this->respondOK($result);
    }

    #[OA\Get(summary: 'Returns general foosharing statistics')]
    #[Route('statistics', methods: ['GET'])]
    #[OA\Response(
        response: Response::HTTP_OK,
        description: 'Successful',
        content: new OA\JsonContent(ref: new Model(type: StatisticModel::class))
    )]
    public function listOverallStatistics(): Response
    {
        $statistics = $this->cache->get('foodsharingOverallStatistics', function (ItemInterface $cacheItem) {
            $cacheItem->expiresAfter(self::OVERALL_STATISTICS_CACHE_DURATION);

            return new StatisticModel(
                new GeneralStatistic(
                    $this->statisticsGateway->listTotalStat(),
                    $this->statisticsGateway->countAllBaskets(),
                    $this->statisticsGateway->avgWeeklyBaskets(),
                    $this->statisticsGateway->countAllFoodsharers(),
                    $this->statisticsGateway->countActiveFoodSharePoints(),
                    $this->statisticsGateway->avgDailyFetchCount()
                ),
                new PickupModel($this->statisticsGateway->listStatRegions()),
            );
        });

        return $this->respondOK($statistics);
    }

    private function isRegion(int $regionId): bool
    {
        return !empty($this->regionGateway->getRegion($regionId));
    }
}
