<?php

declare(strict_types=1);

namespace Foodsharing\RestApi;

use Foodsharing\Lib\Session;
use Foodsharing\Modules\Activity\ActivityTransactions;
use Foodsharing\RestApi\Models\Activities\ActivityFilterModel;
use Foodsharing\RestApi\Models\Activities\ActivityModel;
use Foodsharing\RestApi\Models\Activities\ActivityUpdateModel;
use Foodsharing\RestApi\Models\HttpCodeMessageModel;
use FOS\RestBundle\Controller\AbstractFOSRestController;
use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\Request\ParamFetcher;
use Nelmio\ApiDocBundle\Annotation\Model;
use OpenApi\Attributes as OA;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

class ActivityRestController extends AbstractFOSRestController
{
    public function __construct(
        private readonly ActivityTransactions $activityTransactions,
        private readonly Session $session
    ) {
    }

    #[OA\Get(summary: 'Returns the filters for all dashboard activities for the current user')]
    #[Rest\Get(path: 'activities/filters')]
    #[OA\Tag('activities')]
    #[OA\Response(
        response: Response::HTTP_OK,
        description: 'Successful',
        content: new OA\JsonContent(ref: new Model(type: ActivityModel::class))
    )]
    #[OA\Response(
        response: Response::HTTP_UNAUTHORIZED,
        description: 'Insufficient permissions to request filters.',
        content: new OA\JsonContent(ref: new Model(type: HttpCodeMessageModel::class))
    )]
    public function getActivityFilters(): Response
    {
        if (!$this->session->id()) {
            throw new UnauthorizedHttpException('');
        }

        $filters = $this->activityTransactions->getFilters();

        return $this->handleView($this->view($filters, Response::HTTP_OK));
    }

    #[OA\Patch(summary: 'Sets which dashboard activities should be deactivated for the current user.')]
    #[Rest\Patch(path: 'activities/filters')]
    #[OA\Tag('activities')]
    #[OA\RequestBody(content: new Model(type: ActivityFilterModel::class))]
    #[ParamConverter(
        data: 'activityExcluded',
        class: 'Foodsharing\RestApi\Models\Activities\ActivityFilterModel',
        converter: 'fos_rest.request_body'
    )]
    #[OA\Response(response: Response::HTTP_OK, description: 'Successful')]
    #[OA\Response(
        response: Response::HTTP_UNAUTHORIZED,
        description: 'Insufficient permissions to set request filters.',
        content: new OA\JsonContent(ref: new Model(type: HttpCodeMessageModel::class))
    )]
    #[OA\Response(
        response: Response::HTTP_BAD_REQUEST,
        description: 'Incomplete or incorrect request',
        content: new OA\JsonContent(ref: new Model(type: HttpCodeMessageModel::class))
    )]
    public function setActivityFilters(ActivityFilterModel $activityExcluded): Response
    {
        if (!$this->session->id()) {
            throw new UnauthorizedHttpException('');
        }

        if (!isset($activityExcluded->excluded)) {
            throw new BadRequestException('Incomplete or incorrect request parameters');
        }

        $this->activityTransactions->setExcludedFilters($activityExcluded->excluded);

        return $this->handleView($this->view([], Response::HTTP_OK));
    }

    #[OA\Get(summary: 'Returns the updates object for ActivityOverview to display on the dashboard')]
    #[Rest\Get(path: 'activities/updates')]
    #[OA\Tag('activities')]
    #[Rest\QueryParam(name: 'page', requirements: '\d+', default: 0, description: 'Which page of updates to return')]
    #[OA\Response(
        response: Response::HTTP_OK,
        description: 'Successful',
        content: new OA\JsonContent(ref: new Model(type: ActivityUpdateModel::class))
    )]
    #[OA\Response(
        response: Response::HTTP_UNAUTHORIZED,
        description: 'Insufficient permissions to request filters.',
        content: new OA\JsonContent(ref: new Model(type: HttpCodeMessageModel::class))
    )]
    public function getActivityUpdates(ParamFetcher $paramFetcher): Response
    {
        if (!$this->session->id()) {
            throw new UnauthorizedHttpException('');
        }

        $page = intval($paramFetcher->get('page'));
        $updates = new ActivityUpdateModel($this->activityTransactions->getUpdateData($page));

        return $this->handleView($this->view($updates, Response::HTTP_OK));
    }
}
