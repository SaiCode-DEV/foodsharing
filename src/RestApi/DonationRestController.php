<?php

declare(strict_types=1);

namespace Foodsharing\RestApi;

use Foodsharing\Lib\Session;
use Foodsharing\Modules\Donation\DonationData;
use Foodsharing\Modules\Donation\DonationGateway;
use Foodsharing\Modules\Donation\DonationTransactions;
use Foodsharing\Permissions\DonationPermissions;
use Foodsharing\RestApi\Models\Donation\DonationDataResponse;
use Nelmio\ApiDocBundle\Annotation\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag(name: 'donation')]
final class DonationRestController extends AbstractFoodsharingRestController
{
    public function __construct(
        Session $session,
        private readonly DonationTransactions $donationTransactions,
        private readonly DonationPermissions $donationPermissions,
        private readonly DonationGateway $donationGateway,
    ) {
        parent::__construct($session);
    }

    #[OA\Get(summary: 'Returns cached information of foodsharing donation-goal via third service provider twingle.')]
    #[Route('/donation-data', methods: ['GET'])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success', content: new Model(type: DonationDataResponse::class))]
    #[OA\Response(response: Response::HTTP_SERVICE_UNAVAILABLE, description: 'Currently unavailable because there is no donation campaign')]
    public function getInformation(): Response
    {
        $information = $this->donationTransactions->getDonationInformation();

        return $this->respondOK($information);
    }

    #[OA\Patch(summary: 'Updates cached information of foodsharing donation-goal via third service provider twingle.')]
    #[Route('/donation-data', methods: ['PATCH'])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success', content: new Model(type: DonationData::class))]
    #[OA\Response(response: Response::HTTP_SERVICE_UNAVAILABLE, description: 'Currently unavailable because there is no donation campaign')]
    public function patchInformation(#[MapRequestPayload] DonationData $patchInformation): Response
    {
        $this->assertLoggedIn();

        if (!$this->donationPermissions->mayEditDonationPage()) {
            throw new AccessDeniedHttpException('Not enough permissions to access this resource');
        }

        $this->donationGateway->saveDonation($patchInformation);

        return $this->respondOK();
    }

    #[OA\Get(summary: 'Returns information about donation-projects via third service provider twingle.')]
    #[Route('/donation-projects', methods: ['GET'])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success')]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Not enough permissions to access this resource')]
    #[OA\Response(response: Response::HTTP_SERVICE_UNAVAILABLE, description: 'Currently unavailable because there is no donation campaign')]
    public function getProjects(): Response
    {
        $this->assertLoggedIn();

        if (!$this->donationPermissions->mayEditDonationPage()) {
            throw new AccessDeniedHttpException('Not enough permissions to access this resource');
        }

        $projects = $this->donationTransactions->getProjects();

        return $this->respondOK($projects);
    }
}
