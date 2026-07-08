<?php

namespace Foodsharing\RestApi;

use Foodsharing\Lib\Session;
use Foodsharing\Modules\Foodsaver\FoodsaverGateway;
use Foodsharing\Modules\Region\ForumGateway;
use Foodsharing\Modules\Report\DTO\AddReportData;
use Foodsharing\Modules\Report\DTO\ReportForListView;
use Foodsharing\Modules\Report\DTO\UpdateReportData;
use Foodsharing\Modules\Report\ReportGateway;
use Foodsharing\Modules\Report\ReportTransactions;
use Foodsharing\Permissions\ReportPermissions;
use Nelmio\ApiDocBundle\Annotation\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

#[OA\Tag(name: 'report')]
#[OA\Response(response: Response::HTTP_UNAUTHORIZED, description: 'Not logged in')]
class ReportRestController extends AbstractFoodsharingRestController
{
    public function __construct(
        protected Session $session,
        private readonly ReportGateway $reportGateway,
        private readonly ReportPermissions $reportPermissions,
        private readonly ReportTransactions $reportTransactions,
        private readonly FoodsaverGateway $foodsaverGateway,
        private readonly ForumGateway $forumGateway,
    ) {
        parent::__construct($this->session);
    }

    #[OA\Get(summary: 'List reports for a region')]
    #[Route('regions/{regionId}/reports', methods: ['GET'], requirements: ['regionId' => Requirement::POSITIVE_INT])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success', content: new OA\JsonContent(
        type: 'array',
        items: new OA\Items(ref: new Model(type: ReportForListView::class))
    ))]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Not permitted')]
    public function listReportsForRegion(int $regionId): Response
    {
        $this->assertLoggedIn();
        if (!$this->reportPermissions->mayAccessReportsForRegion($regionId)) {
            throw new AccessDeniedHttpException('Not permitted');
        }

        $reports = $this->reportTransactions->getReportsForRegion($regionId);

        return $this->respondOK($reports);
    }

    #[OA\Get(summary: 'List reports for a user')]
    #[Route('users/{userId}/reports', methods: ['GET'], requirements: ['userId' => Requirement::POSITIVE_INT])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success', content: new OA\JsonContent(
        type: 'array',
        items: new OA\Items(ref: new Model(type: ReportForListView::class))
    ))]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Not permitted')]
    public function listReportsForUser(int $userId): Response
    {
        $this->assertLoggedIn();
        if (!$this->reportPermissions->mayAccessReportsForUser($userId)) {
            throw new AccessDeniedHttpException('Not permitted');
        }
        $reports = $this->reportGateway->getReportsByUser($userId);

        return $this->respondOK($reports);
    }

    #[OA\Post(summary: 'Add a new report')]
    #[Route('users/{userId}/reports', methods: ['POST'], requirements: ['userId' => Requirement::POSITIVE_INT])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success')]
    public function addReport(int $userId, #[MapRequestPayload] AddReportData $reportData): Response
    {
        $this->assertLoggedIn();

        if (!$this->foodsaverGateway->foodsaverExists($userId)) {
            throw new NotFoundHttpException('User not found');
        }

        $this->reportTransactions->addReport($userId, $this->session->id(), $reportData);

        return $this->respondOK();
    }

    #[OA\Delete(summary: 'Delete a report')]
    #[Route('reports/{reportId}', methods: ['DELETE'], requirements: ['reportId' => Requirement::POSITIVE_INT])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success')]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Not permitted')]
    public function deleteReport(int $reportId): Response
    {
        $this->assertLoggedIn();
        if (!$this->reportPermissions->mayDeleteReport($reportId)) {
            throw new AccessDeniedHttpException('Not permitted');
        }
        $this->reportGateway->deleteReport($reportId);

        return $this->respondOK();
    }

    #[OA\Patch(summary: 'Update a report status, consequence, or forum thread link')]
    #[Route('reports/{reportId}', methods: ['PATCH'], requirements: ['reportId' => Requirement::POSITIVE_INT])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success')]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Not permitted')]
    public function updateReport(int $reportId, #[MapRequestPayload] UpdateReportData $updateData): Response
    {
        $this->assertLoggedIn();
        if (!$this->reportPermissions->mayAccessReport($reportId)) {
            throw new AccessDeniedHttpException('Not permitted');
        }

        if ($updateData->forumThreadId !== null) {
            $threadInfo = $this->forumGateway->getThreadInfo($updateData->forumThreadId);

            $validRegionId = $this->reportTransactions->getResponsibleRegionIdForReport($reportId);
            if (!$threadInfo || !isset($threadInfo['region_id']) || (int)$threadInfo['region_id'] !== $validRegionId) {
                throw new BadRequestHttpException('reports.invalid_forum_thread_region');
            }
        }

        $this->reportGateway->updateReport($reportId, $updateData);

        return $this->respondOK();
    }
}
