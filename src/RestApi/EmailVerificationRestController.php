<?php

namespace Foodsharing\RestApi;

use Foodsharing\Lib\Session;
use Foodsharing\Modules\EMailVerify\EMailVerificationGateway;
use Foodsharing\Modules\Login\LoginService;
use Foodsharing\RestApi\Models\EMailVerification\VerificationEmailRequest;
use Nelmio\ApiDocBundle\Annotation\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag(name: 'verification')]
class EmailVerificationRestController extends AbstractFoodsharingRestController
{
    public function __construct(
        Session $session,
        private readonly EMailVerificationGateway $emailVerificationGateway,
        private readonly LoginService $loginService,
    ) {
        parent::__construct($session);
    }

    #[OA\Put(summary: 'Sends a new verification email to a specified address if an account with that address exists and is not yet verified')]
    #[Route('/email-verification', methods: ['PUT'])]
    #[OA\RequestBody(content: new Model(type: VerificationEmailRequest::class))]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success')]
    public function requestVerificationEmail(
        #[MapRequestPayload] VerificationEmailRequest $verificationRequest,
        Request $request,
        RateLimiterFactory $requestPasswordResetLimiter
    ): Response {
        $this->checkRateLimit($request, $requestPasswordResetLimiter);

        $userId = $this->emailVerificationGateway->findUserByEmail($verificationRequest->address);
        if ($userId) {
            $this->loginService->newMailActivation($userId);
        }

        return $this->respondOK();
    }
}
