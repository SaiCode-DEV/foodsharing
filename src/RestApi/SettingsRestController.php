<?php

namespace Foodsharing\RestApi;

use Foodsharing\Lib\Session;
use Foodsharing\Modules\Core\DBConstants\Foodsaver\SleepStatus;
use Foodsharing\Modules\Settings\SettingsGateway;
use Foodsharing\Modules\Settings\SettingsTransactions;
use Foodsharing\RestApi\Models\Settings\EmailChangeRequest;
use Foodsharing\RestApi\Models\Settings\PasswordChangeRequest;
use Foodsharing\RestApi\Models\Settings\SleepStatusRequest;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Requirement\Requirement;

#[OA\Tag(name: 'user')]
class SettingsRestController extends AbstractFoodsharingRestController
{
    public function __construct(
        private readonly SettingsGateway $settingsGateway,
        private readonly SettingsTransactions $settingsTransactions,
        protected Session $session
    ) {
        parent::__construct($this->session);
    }

    #[OA\Response(response: Response::HTTP_OK, description: 'Success')]
    #[OA\Response(response: Response::HTTP_BAD_REQUEST, description: 'Invalid mode or parameters')]
    #[OA\Response(response: Response::HTTP_UNAUTHORIZED, description: 'Not logged in')]
    #[OA\Patch(
        summary: 'Sets the current users sleep mode.',
        description: 'For the temporary mode, both "from" and "to" need to be given. Both are assumed to be in the format "d.m.Y". For other modes the two fields will be ignored. Optionally, a message can be added.'
    )]
    #[Route('/user/sleepmode', methods: ['PATCH'])]
    public function setSleepStatus(#[MapRequestPayload] SleepStatusRequest $request): Response
    {
        $this->assertLoggedIn();

        if (!SleepStatus::isValid($request->mode)) {
            throw new BadRequestHttpException('invalid sleep status');
        }

        // check if from and to are needed
        if ($request->mode == SleepStatus::TEMP && ($request->from == null || $request->to == null)) {
            throw new BadRequestHttpException('invalid dates');
        }

        $this->settingsGateway->updateSleepMode($this->session->id(), $request);

        return new Response('', Response::HTTP_NO_CONTENT);
    }

    #[OA\Patch(
        summary: 'Requests that the users login email address will be changed.',
        description: 'This does not permanently change the address yet, but sends
        out the confirmation email. Every user can change their own email address.
        Changing someone elses address requires certain permissions.'
    )]
    #[Route('/user/{userId}/email', requirements: ['userId' => Requirement::POSITIVE_INT], methods: ['PATCH'])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success')]
    #[OA\Response(response: Response::HTTP_BAD_REQUEST, description: 'Empty or invalid parameters')]
    #[OA\Response(response: Response::HTTP_UNAUTHORIZED, description: 'Not logged in')]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Wrong password')]
    public function requestEmailChange(
        int $userId,
        #[MapRequestPayload] EmailChangeRequest $request
    ): Response {
        $this->assertLoggedIn();

        if ($userId == $this->session->id()) {
            $this->settingsTransactions->requestEmailChange($request);
        } else {
            $this->settingsTransactions->changeLoginEmail($request, $userId);
        }

        return $this->respondOK();
    }

    #[OA\Patch(summary: 'Allows users to change their own password.')]
    #[Route('/user/current/password', methods: ['PATCH'])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success')]
    #[OA\Response(response: Response::HTTP_BAD_REQUEST, description: 'The new password is too short')]
    #[OA\Response(response: Response::HTTP_UNAUTHORIZED, description: 'Not logged in')]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'The old password is wrong')]
    public function requestPasswordChangeAction(
        #[MapRequestPayload] PasswordChangeRequest $request
    ): Response {
        $this->assertLoggedIn();
        $this->settingsTransactions->requestPasswordChange($request);

        return $this->respondOK();
    }
}
