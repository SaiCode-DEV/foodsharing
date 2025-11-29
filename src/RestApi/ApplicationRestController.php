<?php

namespace Foodsharing\RestApi;

use Exception;
use Foodsharing\Lib\Session;
use Foodsharing\Modules\Application\ApplicationTransactions;
use Foodsharing\Modules\Region\RegionGateway;
use Foodsharing\Permissions\WorkGroupPermissions;
use FOS\RestBundle\Controller\Annotations as Rest;
use OpenApi\Annotations as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ApplicationRestController extends AbstractFoodsharingRestController
{
    private readonly RegionGateway $regionGateway;
    private readonly WorkGroupPermissions $workGroupPermissions;
    private readonly ApplicationTransactions $applicationTransactions;

    public function __construct(
        WorkGroupPermissions $workGroupPermissions,
        RegionGateway $regionGateway,
        ApplicationTransactions $applicationTransactions,
        Session $session
    ) {
        parent::__construct($session);

        $this->workGroupPermissions = $workGroupPermissions;
        $this->regionGateway = $regionGateway;
        $this->applicationTransactions = $applicationTransactions;
    }

    /**
     * Accepts an application for a work group.
     *
     * @OA\Tag(name="application")
     * @OA\Parameter(name="groupId", in="path", @OA\Schema(type="integer"), description="which work group the request is for")
     * @OA\Response(response="200", description="success")
     * @OA\Response(response="403", description="Insufficient permissions")
     * @OA\Response(response="404", description="Workgroup does not exist.")
     */
    #[Rest\Patch('applications/{groupId}/{userId}', requirements: ['groupId' => '\d+', 'userId' => '\d+'])]
    public function acceptApplication(int $groupId, int $userId): Response
    {
        $this->assertLoggedIn();

        try {
            $group = $this->regionGateway->getRegion($groupId);
        } catch (Exception) {
            throw new NotFoundHttpException();
        }

        if (!$this->workGroupPermissions->mayEdit($group)) {
            throw new AccessDeniedHttpException();
        }

        $this->applicationTransactions->acceptApplication($group, $userId);

        return $this->respondOK();
    }

    /**
     * Declines an application for a work group.
     *
     * @OA\Tag(name="application")
     * @OA\Parameter(name="groupId", in="path", @OA\Schema(type="integer"), description="which work group the request is for")
     * @OA\Response(response="200", description="Success")
     * @OA\Response(response="403", description="Insufficient permissions")
     * @OA\Response(response="404", description="Workgroup does not exist.")
     */
    #[Rest\Delete('applications/{groupId}/{userId}', requirements: ['groupId' => '\d+', 'userId' => '\d+'])]
    public function declineApplication(int $groupId, int $userId): Response
    {
        $this->assertLoggedIn();

        try {
            $group = $this->regionGateway->getRegion($groupId);
        } catch (Exception) {
            throw new NotFoundHttpException();
        }

        if (!$this->workGroupPermissions->mayEdit($group)) {
            throw new AccessDeniedHttpException();
        }

        $this->applicationTransactions->declineApplication($group, $userId);

        return $this->respondOK();
    }

    /**
     * Returns all pending applications for a working group.
     *
     * @OA\Tag(name="application")
     * @OA\Parameter(name="groupId", in="path", @OA\Schema(type="integer"), description="for which working group to list the applications")
     * @OA\Response(response="200", description="Success")
     * @OA\Response(response="403", description="Insufficient permissions")
     * @OA\Response(response="404", description="Group does not exist.")
     */
    #[Rest\Get('applications/{groupId}', requirements: ['groupId' => '\d+'])]
    public function listApplications(int $groupId): Response
    {
        $this->assertLoggedIn();

        try {
            $group = $this->regionGateway->getRegion($groupId);
        } catch (Exception) {
            throw new NotFoundHttpException();
        }

        if (!$this->workGroupPermissions->mayEdit($group)) {
            throw new AccessDeniedHttpException();
        }

        $applicants = $this->regionGateway->listApplicants($groupId);

        return $this->respondOK($applicants);
    }
}
