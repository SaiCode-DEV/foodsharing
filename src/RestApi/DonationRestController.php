<?php

declare(strict_types=1);

namespace Foodsharing\RestApi;

use Foodsharing\Lib\Session;
use Foodsharing\Modules\Donation\DonationTransactions;
use Foodsharing\RestApi\Models\Donation\DonationInformation;
use Nelmio\ApiDocBundle\Annotation\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag(name: 'donation')]
final class DonationRestController extends AbstractFoodsharingRestController
{
    public function __construct(
        Session $session,
        private readonly DonationTransactions $donationTransactions,
    ) {
        parent::__construct($session);
    }

    #[OA\Get(summary: 'Returns cached information of foodsharing donation-goal via third service provider twingle.')]
    #[Route('/donation-data', methods: ['GET'])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success', content: new Model(type: DonationInformation::class))]
    #[OA\Response(response: Response::HTTP_SERVICE_UNAVAILABLE, description: 'Currently unavailable because there is no donation campaign')]
    public function getInformation(): Response
    {
        $information = $this->donationTransactions->getDonationInformation();

        return $this->respondOK($information);
    }
}
