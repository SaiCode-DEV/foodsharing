<?php

namespace Foodsharing\RestApi;

use Foodsharing\Modules\Region\RegionGateway;
use Foodsharing\Modules\Statistics\DTO\StatisticsAgeBand;
use Foodsharing\Modules\Statistics\DTO\StatisticsGender;
use Foodsharing\Modules\Statistics\StatisticsGateway;
use FOS\RestBundle\Controller\AbstractFOSRestController;
use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\Request\ParamFetcher;
use Nelmio\ApiDocBundle\Annotation\Model;
use OpenApi\Attributes as OA2;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class StatisticRestController extends AbstractFOSRestController
{
    public function __construct(
        private readonly StatisticsGateway $statisticsGateway,
        private readonly RegionGateway $regionGateway,
    ) {
    }

    /**
     * Returns the genderdistribution from a region.
     * If homeregion is set only the homeregion of foodsavers from this regionId are considered.
     */
    #[OA2\Tag(name: 'statistics')]
    #[Rest\Get('statistics/regions/{regionId<\d+>}/gender')]
    #[OA2\Response(response: '200', description: 'Success', content: new OA2\JsonContent(
        type: 'array',
        items: new OA2\Items(ref: new Model(type: StatisticsGender::class)))
    )]
    #[Rest\QueryParam(name: 'homeRegion', requirements: 'true|false', default: false, description: 'result limit to homeregion')]
    public function getStatisticRegionGender(
        int $regionId,
        ParamFetcher $paramFetcher
    ): Response {
        $region = $this->regionGateway->getRegion($regionId);
        if (empty($region)) {
            throw new NotFoundHttpException('region does not exist');
        }
        $homeRegion = $paramFetcher->get('homeRegion');

        // Cast $homeRegion to boolean
        $homeRegion = filter_var($homeRegion, FILTER_VALIDATE_BOOLEAN);

        if ($homeRegion) {
            $result = $this->statisticsGateway->genderCountHomeRegion($regionId);
        } else {
            $result = $this->statisticsGateway->genderCountRegion($regionId);
        }

        return $this->handleView($this->view($result, 200));
    }

    /**
     * Returns the age band distribution from a region.
     * If homeregion is set only the homeregion of foodsavers from this regionId are considered.
     */
    #[OA2\Tag(name: 'statistics')]
    #[Rest\Get('statistics/regions/{regionId<\d+>}/age-band')]
    #[OA2\Response(response: '200', description: 'Success', content: new OA2\JsonContent(
        type: 'array',
        items: new OA2\Items(ref: new Model(type: StatisticsAgeBand::class)))
    )]
    #[Rest\QueryParam(name: 'homeRegion', requirements: 'true|false', default: false, description: 'result limit to homeregion')]
    public function getStatisticRegionAgeBand(
        int $regionId,
        ParamFetcher $paramFetcher
    ): Response {
        $region = $this->regionGateway->getRegion($regionId);
        if (empty($region)) {
            throw new NotFoundHttpException('region does not exist');
        }
        $homeRegion = $paramFetcher->get('homeRegion');

        // Cast $homeRegion to boolean
        $homeRegion = filter_var($homeRegion, FILTER_VALIDATE_BOOLEAN);

        if ($homeRegion) {
            $result = $this->statisticsGateway->ageBandHomeDistrict($regionId);
        } else {
            $result = $this->statisticsGateway->ageBandDistrict($regionId);
        }

        return $this->handleView($this->view($result, 200));
    }
}
