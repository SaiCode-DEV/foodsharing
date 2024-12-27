<?php

namespace Foodsharing\RestApi;

use Foodsharing\Lib\Session;
use Foodsharing\Modules\Legal\LegalTransactions;
use Foodsharing\RestApi\Models\Legal\LegalAcknowledge;
use FOS\RestBundle\Controller\Annotations as Rest;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;

#[OA\Tag(name: 'legal')]
class LegalRestController extends AbstractFoodsharingRestController
{
    public function __construct(
        private readonly LegalTransactions $legalTransactions,
        protected Session $session
    ) {
        parent::__construct($this->session);
    }

    #[OA\Patch(summary: 'Change privacy policy and privacy notice acknowledge.')]
    #[Rest\Patch('legal/acknowledge')]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success')]
    #[OA\Response(response: Response::HTTP_BAD_REQUEST, description: 'The new password is too short')]
    #[OA\Response(response: Response::HTTP_UNAUTHORIZED, description: 'Not logged in')]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'The old password is wrong')]
    public function updateLegalAcknowlegeAction(#[MapRequestPayload] LegalAcknowledge $legalAcknowledge): Response
    {
        $this->assertLoggedIn();

        $this->legalTransactions->updateLegalAcknowledge($this->session->id(), $legalAcknowledge);

        return $this->respondOK();
    }

    #[OA\Get(summary: 'Get legal page data')]
    #[Rest\Get('legal/page-data')]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success')]
    public function getLegalPageData(): Response
    {
        $data = $this->legalTransactions->getLegalPageData($this->session->id());

        return $this->respondOK($data);
    }
}
