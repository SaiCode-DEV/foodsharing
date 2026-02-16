<?php

declare(strict_types=1);

namespace Foodsharing\RestApi;

use Foodsharing\Lib\Session;
use Foodsharing\Modules\Activity\ActivityTransactions;
use Foodsharing\Modules\Activity\DTO\ActivityUpdate;
use Foodsharing\Modules\Activity\DTO\ActivityUpdateMailbox;
use Foodsharing\RestApi\Models\Activities\ActivityFilterModel;
use Foodsharing\RestApi\Models\Activities\ActivityModel;
use Nelmio\ApiDocBundle\Annotation\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag('activities')]
#[OA\Response(response: Response::HTTP_UNAUTHORIZED, description: 'Not logged in')]
class ActivityRestController extends AbstractFoodsharingRestController
{
    public function __construct(
        private readonly ActivityTransactions $activityTransactions,
        protected Session $session
    ) {
        parent::__construct($session);
    }

    #[OA\Get(summary: 'Returns the filters for all dashboard activities for the current user')]
    #[Route('activities/filters', methods: ['GET'])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success', content: new OA\JsonContent(
        ref: new Model(type: ActivityModel::class)
    ))]
    public function getActivityFilters(): Response
    {
        $this->assertLoggedIn();

        $filters = $this->activityTransactions->getFilters();

        return $this->respondOK($filters);
    }

    #[OA\Patch(summary: 'Sets which dashboard activities should be deactivated for the current user.')]
    #[Route('activities/filters', methods: ['PATCH'])]
    #[OA\RequestBody(content: new Model(type: ActivityFilterModel::class))]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success')]
    #[OA\Response(response: Response::HTTP_BAD_REQUEST, description: 'Incomplete or incorrect request parameters')]
    public function setActivityFilters(#[MapRequestPayload] ActivityFilterModel $activityExcluded): Response
    {
        $this->assertLoggedIn();

        if (!isset($activityExcluded->excluded)) {
            throw new BadRequestException('Incomplete or incorrect request parameters');
        }

        $this->activityTransactions->setExcludedFilters($activityExcluded->excluded);

        return $this->respondOK();
    }

    #[OA\Get(summary: 'Returns the updates to display on the dashboard')]
    #[Route('activities/updates', methods: ['GET'])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success', content: new OA\JsonContent(
        type: 'array',
        description: 'The list of achievements scoped to this region.',
        items: new OA\Items(oneOf: [
            new OA\Property(
                ref: new Model(type: ActivityUpdate::class),
                title: 'All updates, except mailbox'
            ),
            new OA\Property(
                ref: new Model(type: ActivityUpdateMailbox::class),
                title: 'Contains all mailbox updates'
            ),
        ])
    ))]
    public function getActivityUpdates(#[MapQueryParameter(options: ['min_range' => 0])] int $page = 0): Response
    {
        // TODO unify pagination
        // Currently, the page size is hardcoded and per activity type.
        $this->assertLoggedIn();

        $updates = $this->activityTransactions->getUpdateData($page);

        return $this->respondOK($updates);
    }
}
