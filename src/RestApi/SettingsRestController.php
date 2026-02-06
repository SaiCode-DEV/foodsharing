<?php

namespace Foodsharing\RestApi;

use Foodsharing\Lib\Session;
use Foodsharing\Modules\Core\DBConstants\Foodsaver\SleepStatus;
use Foodsharing\Modules\Foodsaver\DTO\ReadableProfileSettings;
use Foodsharing\Modules\Settings\DTO\TOTPProposal;
use Foodsharing\Modules\Settings\SettingsGateway;
use Foodsharing\Modules\Settings\SettingsTransactions;
use Foodsharing\RestApi\Models\Settings\EmailChangeRequest;
use Foodsharing\RestApi\Models\Settings\PasswordChangeRequest;
use Foodsharing\RestApi\Models\Settings\SleepStatusRequest;
use Foodsharing\RestApi\Models\Settings\TwoFARequest;
use Foodsharing\Utility\Requirement as FSRequirement;
use Nelmio\ApiDocBundle\Annotation\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag(name: 'settings')]
#[OA\Response(response: Response::HTTP_UNAUTHORIZED, description: 'Not logged in')]
class SettingsRestController extends AbstractFoodsharingRestController
{
    public function __construct(
        private readonly SettingsGateway $settingsGateway,
        private readonly SettingsTransactions $settingsTransactions,
        protected Session $session
    ) {
        parent::__construct($this->session);
    }

    #[OA\Patch(
        summary: 'Sets the current users sleep mode.',
        description: 'For the temporary mode, both "from" and "to" need to be given. Both are assumed to be in the format "d.m.Y". For other modes the two fields will be ignored. Optionally, a message can be added.'
    )]
    #[Route('/users/current/sleep-mode', methods: ['PATCH'])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success')]
    #[OA\Response(response: Response::HTTP_BAD_REQUEST, description: 'Invalid mode or parameters')]
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

        return $this->respondOK();
    }

    #[OA\Patch(
        summary: 'Requests that the users login email address will be changed.',
        description: 'This does not permanently change the address yet, but sends
        out the confirmation email. Every user can change their own email address.
        Changing someone elses address requires certain permissions.'
    )]
    #[Route('/users/{userId}/email', requirements: ['userId' => FSRequirement::USER_ID], methods: ['PATCH'])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success')]
    #[OA\Response(response: Response::HTTP_BAD_REQUEST, description: 'Empty or invalid parameters')]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Not permitted or wrong password')]
    public function requestEmailChange(#[MapRequestPayload] EmailChangeRequest $request, string $userId): Response
    {
        $this->assertLoggedIn();
        $userId = $this->resolveUserId($userId);

        if ($userId == $this->session->id()) {
            $this->settingsTransactions->requestEmailChange($request);
        } else {
            $this->settingsTransactions->changeLoginEmail($request, $userId);
        }

        return $this->respondOK();
    }

    #[OA\Patch(summary: 'Changes the user\'s password.')]
    #[Route('/users/current/password', methods: ['PATCH'])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success')]
    #[OA\Response(response: Response::HTTP_BAD_REQUEST, description: 'The new password is invalid')]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'The old password is wrong')]
    public function requestPasswordChange(#[MapRequestPayload] PasswordChangeRequest $request): Response
    {
        $this->assertLoggedIn();
        $this->settingsTransactions->requestPasswordChange($request);

        return $this->respondOK();
    }

    #[OA\Post(summary: 'Request 2FA secret, backup codes and QR code.')]
    #[Route('/users/current/2fa', methods: ['POST'])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success', content: new Model(type: TOTPProposal::class))]
    public function generateTwoFA(): Response
    {
        $this->assertLoggedIn();
        $data = $this->settingsTransactions->generateTwoFA();

        return $this->respondOK($data);
    }

    #[OA\Patch(summary: 'Edit 2FA settings.')]
    #[Route('/users/{userId}/2fa', requirements: ['userId' => FSRequirement::USER_ID], methods: ['PATCH'])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success')]
    #[OA\Response(response: Response::HTTP_BAD_REQUEST, description: 'Invalid parameters')]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Insufficient permissions')]
    public function toggleTwoFA(#[MapRequestPayload] TwoFARequest $request, string $userId): Response
    {
        $this->assertLoggedIn();

        $userId = $this->resolveUserId($userId);

        if ($request->enable) {
            if ($userId !== $this->session->id()) {
                throw new BadRequestHttpException('insufficient permissions');
            }
            // Enable 2FA
            $this->settingsTransactions->enableTwoFA($request->code, $request->password);
        } else {
            // Disable 2FA
            $this->settingsTransactions->disableTwoFA($request->code, $request->password, $userId);
        }

        return $this->respondOK();
    }

    #[OA\Get(summary: 'Get the user profile information.')]
    #[Route('users/{userId}/profile-settings', methods: ['GET'], requirements: ['userId' => FSRequirement::USER_ID])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success.', content: new Model(type: ReadableProfileSettings::class))]
    #[OA\Response(response: Response::HTTP_BAD_REQUEST, description: 'Bad Request.')]
    #[OA\Response(response: Response::HTTP_NOT_FOUND, description: 'User not found.')]
    public function getUserProfileSettings(string $userId): Response
    {
        $this->assertLoggedIn();

        $userId = $this->resolveUserId($userId);

        $data = $this->settingsTransactions->readProfile($userId);

        return $this->respondOK($data);
    }
}
