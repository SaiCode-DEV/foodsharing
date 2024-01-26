<?php

namespace Foodsharing\RestApi;

use Foodsharing\Lib\Session;
use Foodsharing\Modules\FoodSharePoint\FoodSharePointGateway;
use Foodsharing\Modules\Region\RegionGateway;
use Foodsharing\Permissions\RegionPermissions;
use FOS\RestBundle\Controller\AbstractFOSRestController;
use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\Request\ParamFetcher;
use OpenApi\Annotations as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * Rest controller for food share points.
 */
final class FoodSharePointRestController extends AbstractFOSRestController
{
    private const NOT_LOGGED_IN = 'not logged in';
    private const MAX_FSP_DISTANCE = 50;

    public function __construct(
        private readonly FoodSharePointGateway $foodSharePointGateway,
        private readonly RegionGateway $regionGateway,
        private readonly RegionPermissions $regionPermissions,
        private readonly Session $session
    ) {
    }

    /**
     * Returns a list of food share points close to a given location. If the location is not valid the user's
     * home location is used. The distance is measured in kilometers.
     *
     * Returns 200 and a list of food share points, 400 if the distance is out of range, or 401 if not logged in.
     *
     * @OA\Tag(name="foodsharepoint")
     */
    #[Rest\Get('foodSharePoints/nearby')]
    #[Rest\QueryParam(name: 'lat', nullable: true)]
    #[Rest\QueryParam(name: 'lon', nullable: true)]
    #[Rest\QueryParam(name: 'distance', nullable: false, requirements: '\d+')]
    public function listNearbyFoodSharePoints(ParamFetcher $paramFetcher): Response
    {
        if (!$this->session->mayRole()) {
            throw new UnauthorizedHttpException('', self::NOT_LOGGED_IN);
        }

        $location = $this->fetchLocationOrUserHome($paramFetcher);
        $distance = $paramFetcher->get('distance');
        if ($distance < 1 || $distance > self::MAX_FSP_DISTANCE) {
            throw new BadRequestHttpException('distance must be positive and <= ' . self::MAX_FSP_DISTANCE);
        }

        $fsps = $this->foodSharePointGateway->listNearbyFoodSharePoints($location, $distance);
        $fsps = array_map(fn ($fsp) => $this->normalizeFoodSharePoint($fsp), $fsps);

        return $this->handleView($this->view($fsps, 200));
    }

    /**
     * Returns details of the food share point with the given ID. Returns 200 and the
     * food share point, 404 if the food share point does not exist, or 401 if not logged in.
     *
     * @OA\Tag(name="foodsharepoint")
     */
    #[Rest\Get('foodSharePoints/{foodSharePointId}', requirements: ['foodSharePointId' => '\d+'])]
    public function getFoodSharePoint(int $foodSharePointId): Response
    {
        if (!$this->session->mayRole()) {
            throw new UnauthorizedHttpException('', self::NOT_LOGGED_IN);
        }

        $foodSharePoint = $this->foodSharePointGateway->getFoodSharePoint($foodSharePointId);
        if (!$foodSharePoint || $foodSharePoint['status'] !== 1) {
            throw new NotFoundHttpException('Food share point does not exist or was deleted.');
        }

        $foodSharePoint = $this->normalizeFoodSharePoint($foodSharePoint);

        return $this->handleView($this->view($foodSharePoint, 200));
    }

    private function fetchLocationOrUserHome(ParamFetcher $paramFetcher): array
    {
        $lat = $paramFetcher->get('lat');
        $lon = $paramFetcher->get('lon');
        if (!$this->isValidNumber($lat, -90.0, 90.0) || !$this->isValidNumber($lon, -180.0, 180.0)) {
            // find user's location
            $loc = $this->session->getLocation();
            if (!$loc || (($lat = $loc['lat']) === 0 && ($lon = $loc['lon']) === 0)) {
                throw new BadRequestHttpException('The user profile has no address.');
            }
        }

        return ['lat' => $lat, 'lon' => $lon];
    }

    /**
     * Checks if the number is a valid value in the given range.
     * TODO Duplicated in BasketRestController.php.
     */
    private function isValidNumber($value, float $lowerBound, float $upperBound): bool
    {
        return !is_null($value) && !is_nan($value)
            && ($lowerBound <= $value) && ($upperBound >= $value);
    }

    /**
     * Normalizes the details of a food share point for the Rest response.
     *
     * @param array $data the food share point data
     */
    private function normalizeFoodSharePoint(array $data): array
    {
        // set main properties
        $fsp = [
            'id' => (int)$data['id'],
            'regionId' => (int)$data['bezirk_id'],
            'name' => $data['name'],
            'description' => $data['desc'],
            'address' => $data['anschrift'],
            'city' => $data['ort'],
            'postcode' => $data['plz'],
            'lat' => (float)$data['lat'],
            'lon' => (float)$data['lon'],
            'createdAt' => RestNormalization::normalizeDate($data['time_ts']),
            'picture' => $data['picture']
        ];

        if ($fsp['picture'] == '' || !$fsp['picture']) {
            $fsp['picture'] = null;
        }

        return $fsp;
    }

    /**
     * Returns a list of all food share points in a region and all its subregions.
     *
     * @OA\Tag(name="foodsharepoint")
     * @OA\Parameter(name="regionId", in="path", @OA\Schema(type="integer"), description="region for which to return food share points")
     * @OA\Response(response="200", description="Success")
     * @OA\Response(response="401", description="Not logged in")
     * @OA\Response(response="403", description="Insufficient permission to access the region")
     */
    #[Rest\Get('regions/{regionId}/foodSharePoints', requirements: ['regionId' => '\d+'])]
    public function listFoodSharePoints(int $regionId): Response
    {
        if (!$this->session->mayRole()) {
            throw new UnauthorizedHttpException('', self::NOT_LOGGED_IN);
        }

        if (!$this->regionPermissions->mayListFoodSharePointsInRegion($regionId)) {
            throw new AccessDeniedHttpException('');
        }

        $regionIds = $this->regionGateway->listIdsForDescendantsAndSelf($regionId);
        $foodSharePoints = $this->foodSharePointGateway->listActiveFoodSharePoints($regionIds);

        return $this->handleView($this->view($foodSharePoints, 200));
    }
}
