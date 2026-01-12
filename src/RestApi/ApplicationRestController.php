<?php

namespace Foodsharing\RestApi;

use Exception;
use Foodsharing\Lib\Session;
use Foodsharing\Modules\Application\ApplicationTransactions;
use Foodsharing\Modules\Foodsaver\Profile;
use Foodsharing\Modules\Region\RegionGateway;
use Foodsharing\Permissions\WorkGroupPermissions;
use Nelmio\ApiDocBundle\Annotation\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

#[OA\Tag(name: 'application')]
#[OA\Response(response: Response::HTTP_UNAUTHORIZED, description: 'Not logged in')]
#[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Not permitted')]
#[OA\Response(response: Response::HTTP_NOT_FOUND, description: 'Workgroup does not exist')]
class ApplicationRestController extends AbstractFoodsharingRestController
{
    public function __construct(
        protected Session $session,
        private readonly WorkGroupPermissions $workGroupPermissions,
        private readonly RegionGateway $regionGateway,
        private readonly ApplicationTransactions $applicationTransactions,
    ) {
    }

    #[OA\Patch(summary: 'Accepts an application for a work group')]
    #[Route('applications/{groupId}/{userId}', methods: ['PATCH'], requirements: ['groupId' => Requirement::POSITIVE_INT, 'userId' => Requirement::POSITIVE_INT])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success')]
    public function acceptApplication(int $groupId, int $userId): Response
    {
        $this->assertLoggedIn();

        try {
            $group = $this->regionGateway->getRegion($groupId);
        } catch (Exception) {
            throw new NotFoundHttpException('Workgroup does not exist.');
        }

        if (!$this->workGroupPermissions->mayEdit($group)) {
            throw new AccessDeniedHttpException('Not permitted');
        }

        $this->applicationTransactions->acceptApplication($group, $userId);

        return $this->respondOK();
    }

    #[OA\Delete(summary: 'Declines an application for a work group')]
    #[Route('applications/{groupId}/{userId}', methods: ['DELETE'], requirements: ['groupId' => Requirement::POSITIVE_INT, 'userId' => Requirement::POSITIVE_INT])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success')]
    public function declineApplication(int $groupId, int $userId): Response
    {
        $this->assertLoggedIn();

        try {
            $group = $this->regionGateway->getRegion($groupId);
        } catch (Exception) {
            throw new NotFoundHttpException('Workgroup does not exist.');
        }

        if (!$this->workGroupPermissions->mayEdit($group)) {
            throw new AccessDeniedHttpException('Not permitted');
        }

        $this->applicationTransactions->declineApplication($group, $userId);

        return $this->respondOK();
    }

    #[OA\Get(summary: 'Returns all pending applications for a working group')]
    #[Route('applications/{groupId}', methods: ['GET'], requirements: ['groupId' => Requirement::POSITIVE_INT])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success', content: new OA\JsonContent(
        type: 'array',
        items: new OA\Items(ref: new Model(type: Profile::class)),
        description: 'The list of pending applicants.'
    ))]
    public function listApplications(int $groupId): Response
    {
        $this->assertLoggedIn();

        try {
            $group = $this->regionGateway->getRegion($groupId);
        } catch (Exception) {
            throw new NotFoundHttpException('Workgroup does not exist.');
        }

        if (!$this->workGroupPermissions->mayEdit($group)) {
            throw new AccessDeniedHttpException('Not permitted');
        }

        $applications = $this->regionGateway->listApplications($groupId);

        return $this->respondOK($applications);
    }
}
