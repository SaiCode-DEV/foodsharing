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
use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\Request\ParamFetcher;
use Nelmio\ApiDocBundle\Annotation\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class StatisticRestController extends AbstractFoodsharingRestController
{
    private const string NOT_FOUND_MESSAGE = 'Region with that id %d not found';
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
        summary: 'Returns the gender distribution from a region.'
    )]
    #[Rest\Get(path: 'statistics/regions/{regionId<\d+>}/gender')]
    #[OA\Tag(name: 'statistics')]
    #[Rest\QueryParam(
        name: 'homeRegion',
        requirements: 'true|false',
        default: false,
        description: 'result limit to home region'
    )]
    #[OA\Response(
        response: Response::HTTP_OK,
        description: 'Successful',
        content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: new Model(type: StatisticsGender::class)))
    )]
    #[OA\Response(response: Response::HTTP_NOT_FOUND, description: self::NOT_FOUND_MESSAGE)]
    public function listRegionGenderStatistic(int $regionId, ParamFetcher $paramFetcher): Response
    {
        if (!$this->isRegion($regionId)) {
            throw new NotFoundHttpException(sprintf(self::NOT_FOUND_MESSAGE, $regionId));
        }

        $result = $this->isHomeRegion($paramFetcher->get('homeRegion'))
            ? $this->statisticsGateway->genderCountHomeRegion($regionId)
            : $this->statisticsGateway->genderCountRegion($regionId);

        return $this->respondOK($result);
    }

    #[OA\Get(
        description: 'If home region is set only the home region of foodsavers from this regionId are considered.',
        summary: 'Returns the age band distribution from a region.',
    )]
    #[OA\Tag(name: 'statistics')]
    #[Rest\Get('statistics/regions/{regionId<\d+>}/age-band')]
    #[Rest\QueryParam(
        name: 'homeRegion',
        requirements: 'true|false',
        default: false,
        description: 'result limit to home region'
    )]
    #[OA\Response(
        response: Response::HTTP_OK,
        description: 'Successful',
        content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: new Model(type: StatisticsAgeBand::class)))
    )]
    #[OA\Response(response: Response::HTTP_NOT_FOUND, description: self::NOT_FOUND_MESSAGE)]
    public function listRegionAgeBandStatistic(int $regionId, ParamFetcher $paramFetcher): Response
    {
        if (!$this->isRegion($regionId)) {
            throw new NotFoundHttpException(sprintf(self::NOT_FOUND_MESSAGE, $regionId));
        }

        $result = $this->isHomeRegion($paramFetcher->get('homeRegion'))
            ? $this->statisticsGateway->ageBandHomeDistrict($regionId)
            : $this->statisticsGateway->ageBandDistrict($regionId);

        return $this->respondOK($result);
    }

    #[OA\Get(summary: 'Returns the age band distribution from a region.')]
    #[OA\Tag(name: 'statistics')]
    #[Rest\Get('statistics/regions/{regionId<\d+>}/pickups')]
    #[OA\Response(
        response: Response::HTTP_OK,
        description: 'Successful',
        content: new OA\JsonContent(type: RegionPickupStatistics::class)
    )]
    #[OA\Response(response: Response::HTTP_NOT_FOUND, description: self::NOT_FOUND_MESSAGE)]
    public function listRegionPickupsStatistics(int $regionId): Response
    {
        $region = $this->regionGateway->getRegion($regionId);
        if (empty($region)) {
            throw new NotFoundHttpException(sprintf(self::NOT_FOUND_MESSAGE, $regionId));
        }
        if ($region['type'] === UnitType::COUNTRY && !$this->regionPermissions->mayAccessStatisticCountry()) {
            throw new AccessDeniedHttpException();
        }

        $result = $this->regionTransactions->getRegionPickupStatistics($regionId);

        return $this->respondOK($result);
    }

    #[OA\Get(summary: 'Returns the age band distribution from a region.')]
    #[OA\Tag(name: 'statistics')]
    #[Rest\Get('statistics')]
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
                $this->getGeneralStatistic(),
                $this->getRegionsStatistic(),
            );
        });

        return $this->respondOK($statistics);
    }

    private function getGeneralStatistic(): GeneralStatistic
    {
        return new GeneralStatistic(
            $this->statisticsGateway->listTotalStat(),
            $this->statisticsGateway->countAllBaskets(),
            $this->statisticsGateway->avgWeeklyBaskets(),
            $this->statisticsGateway->countAllFoodsharers(),
            $this->statisticsGateway->countActiveFoodSharePoints(),
            $this->statisticsGateway->avgDailyFetchCount()
        );
    }

    private function getRegionsStatistic(): PickupModel
    {
        return new PickupModel(
            $this->statisticsGateway->listStatRegions(),
        );
    }

    private function isRegion(int $regionId): bool
    {
        return !empty($this->regionGateway->getRegion($regionId));
    }

    private function isHomeRegion(bool|string $homeRegion): bool
    {
        return filter_var($homeRegion, FILTER_VALIDATE_BOOLEAN, ['flags' => FILTER_REQUIRE_SCALAR]);
    }
}
