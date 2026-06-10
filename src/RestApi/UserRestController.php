<?php

namespace Foodsharing\RestApi;

use Carbon\Carbon;
use Exception;
use Foodsharing\Lib\Session;
use Foodsharing\Modules\Foodsaver\DTO\EditableProfileDTO;
use Foodsharing\Modules\Foodsaver\DTO\ProfileDetails;
use Foodsharing\Modules\Foodsaver\FoodsaverGateway;
use Foodsharing\Modules\Foodsaver\FoodsaverTransactions;
use Foodsharing\Modules\Foodsaver\Profile;
use Foodsharing\Modules\Login\DTO\LoginRequest;
use Foodsharing\Modules\Login\LoginGateway;
use Foodsharing\Modules\Logout\LogoutTransactions;
use Foodsharing\Modules\Profile\DTO\DeleteProfileRequest;
use Foodsharing\Modules\Profile\DTO\EmailAddress;
use Foodsharing\Modules\Profile\DTO\PasswordResetRequest;
use Foodsharing\Modules\Profile\ProfileTransactions;
use Foodsharing\Modules\Register\DTO\RegisterData;
use Foodsharing\Modules\Register\DTO\RegisterResult;
use Foodsharing\Modules\Register\RegisterTransactions;
use Foodsharing\Modules\Settings\SettingsTransactions;
use Foodsharing\Modules\Store\DTO\CommonLabel;
use Foodsharing\Permissions\ProfilePermissions;
use Foodsharing\Permissions\UploadsPermissions;
use Foodsharing\RestApi\Models\UUID;
use Foodsharing\Utility\EmailHelper;
use Foodsharing\Utility\Requirement as FsRequirement;
use Nelmio\ApiDocBundle\Annotation\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag(name: 'user')]
class UserRestController extends AbstractFoodsharingRestController
{
    private const int MIN_AGE_YEARS = 18;
    private const int DELETE_USER_MAX_REASON_LEN = 200;

    public function __construct(
        protected Session $session,
        private readonly LoginGateway $loginGateway,
        private readonly FoodsaverGateway $foodsaverGateway,
        private readonly EmailHelper $emailHelper,
        private readonly RegisterTransactions $registerTransactions,
        private readonly ProfileTransactions $profileTransactions,
        private readonly FoodsaverTransactions $foodsaverTransactions,
        private readonly ProfilePermissions $profilePermissions,
        private readonly UploadsPermissions $uploadsPermissions,
        private readonly SettingsTransactions $settingsTransactions,
        private readonly LogoutTransactions $logoutTransactions,
    ) {
        parent::__construct($session);
    }

    #[OA\Get(summary: 'Lists basic information for a user')]
    #[Route('users/{userId}', methods: ['GET'], requirements: ['userId' => FsRequirement::USER_ID])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success', content: new Model(type: Profile::class))]
    #[OA\Response(response: Response::HTTP_UNAUTHORIZED, description: 'Not logged in')]
    #[OA\Response(response: Response::HTTP_NOT_FOUND, description: 'User with that id not found')]
    public function user(string $userId): Response
    {
        $this->assertLoggedIn();
        $userId = $this->resolveUserId($userId);

        $data = $this->foodsaverGateway->getProfile($userId);
        if (empty($data)) {
            throw new NotFoundHttpException('User does not exist.');
        }

        return $this->respondOk($data);
    }

    #[OA\Get(summary: 'Lists detailed information for the current user')]
    #[Route('users/current/details', methods: ['GET'])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success', content: new Model(type: ProfileDetails::class))]
    #[OA\Response(response: Response::HTTP_UNAUTHORIZED, description: 'Not logged in')]
    public function currentUserDetails(): Response
    {
        $this->assertLoggedIn();

        $profileDetails = $this->foodsaverTransactions->getUserDetails($this->session->id());

        return $this->respondOK($profileDetails);
    }

    #[OA\Post(summary: 'Logs in a user with email and password')]
    #[Route('login', methods: ['POST'])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success')]
    #[OA\Response(response: Response::HTTP_UNAUTHORIZED, description: 'Invalid email or password')]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: '2FA code required')]
    #[OA\Response(response: Response::HTTP_CONFLICT, description: 'The account was not activated yet')]
    public function login(#[MapRequestPayload] LoginRequest $loginRequest, Request $request, RateLimiterFactoryInterface $loginLimiter): Response
    {
        $this->checkRateLimit($request, $loginLimiter);

        // Check if 2FA is enabled for this user
        if (empty($loginRequest->code) && $this->loginGateway->hasTOTP(-1, $loginRequest->email)) {
            throw new AccessDeniedHttpException('2FA required');
        }

        $userId = $this->loginGateway->canLogin($loginRequest->email, $loginRequest->password, $loginRequest->code ?? '');
        if (!$userId) {
            throw new UnauthorizedHttpException('', 'email, password or code are invalid');
        }
        if (!$this->loginGateway->isActivated($userId)) {
            throw new ConflictHttpException('Account is not activated yet');
        }
        $this->loginGateway->updateLastActivityInDatabase($userId);
        $this->session->login($userId, $loginRequest->rememberMe);

        return $this->respondOK();
    }

    // Currently unused by the frontend, but included for user API scripts
    #[OA\Post(summary: 'Logs out the current user')]
    #[Route('logout', methods: ['POST'])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success')]
    #[OA\Response(response: Response::HTTP_UNAUTHORIZED, description: 'Not logged in')]
    public function logout(): Response
    {
        $this->assertLoggedIn();
        $this->logoutTransactions->logout();

        return $this->respondOK();
    }

    #[OA\Post(summary: 'Initialises the registration of a new user.')]
    #[Route('users/registration', methods: ['POST'])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success')]
    #[OA\Response(response: Response::HTTP_BAD_REQUEST, description: 'Email is malformed or from a blacklisted domain')]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'There is an ongoing registration process with that e-mail address')]
    public function initialiseRegistration(#[MapRequestPayload] EmailAddress $email): Response
    {
        if (
            !$this->emailHelper->validEmail($email->email)
            || $this->foodsaverGateway->emailDomainIsBlacklisted($email->email)
            || $this->emailHelper->isFoodsharingEmailAddress($email->email)
        ) {
            throw new BadRequestHttpException('email is malformed or from a blacklisted domain');
        }

        $this->registerTransactions->addRegistrationAttempt($email->email);

        return $this->respondOK();
    }

    #[OA\Post(summary: 'Registers a new user')]
    #[Route('users', methods: ['POST'])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success', content: new Model(type: RegisterResult::class))]
    #[OA\Response(response: Response::HTTP_BAD_REQUEST, description: 'Invalid input data')]
    public function registerUser(#[MapRequestPayload] RegisterData $registerData): Response
    {
        $registerData->firstName = trim(strip_tags($registerData->firstName));
        $registerData->lastName = trim(strip_tags($registerData->lastName));

        $registerData->password = trim($registerData->password);
        if (strlen($registerData->password) < SettingsTransactions::MIN_PASSWORD_LENGTH) {
            throw new BadRequestHttpException('password is too short');
        }

        $registerData->birthdate->setTime(0, 0, 0);

        $minBirthdate = Carbon::today()->subYears(self::MIN_AGE_YEARS);
        if ($registerData->birthdate > $minBirthdate) {
            throw new BadRequestHttpException('you are not old enough');
        }

        $registerData->mobilePhone = strip_tags($registerData->mobilePhone ?? '');

        try {
            // register user and send out registration email
            $result = $this->registerTransactions->registerUser($registerData);

            return $this->respondOK($result);
        } catch (Exception $e) {
            throw new HttpException(500, 'could not register user', $e);
        }
    }

    #[OA\Delete(summary: 'Deletes a user account')]
    #[Route('users/{userId}', methods: ['DELETE'], requirements: ['userId' => FsRequirement::USER_ID])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success')]
    #[OA\Response(response: Response::HTTP_BAD_REQUEST, description: 'Invalid input data')]
    #[OA\Response(response: Response::HTTP_UNAUTHORIZED, description: 'Password required when deleting own account')]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Not permitted')]
    public function deleteUser(string $userId, #[MapRequestPayload] DeleteProfileRequest $deleteRequest): Response
    {
        $this->assertLoggedIn();
        $userId = $this->resolveUserId($userId);
        if (!$this->profilePermissions->mayDeleteUser($userId)) {
            throw new AccessDeniedHttpException('Not permitted');
        }

        if (!is_null($deleteRequest->reason)) {
            $deleteRequest->reason = trim($deleteRequest->reason);
            if (strlen($deleteRequest->reason) > self::DELETE_USER_MAX_REASON_LEN) {
                throw new BadRequestHttpException('reason text is too long: must be at most ' . self::DELETE_USER_MAX_REASON_LEN . ' characters');
            }
        }

        // If the user deletes themself, require current password as additional validation
        if ($userId === $this->session->id()) {
            $password = $deleteRequest->password;
            if (empty($password)) {
                throw new BadRequestHttpException('password required');
            }

            // validate password for current user
            $email = $this->foodsaverGateway->getEmailAddress($userId);
            $canLogin = $this->loginGateway->canLogin($email, $password, '');
            if (!$canLogin) {
                throw new UnauthorizedHttpException('', 'Password is incorrect or 2FA is enabled');
            }
        }

        // needs the session ID, so we can't log out just yet
        $this->foodsaverTransactions->deleteFoodsaver($userId, $this->session->id(), $deleteRequest->reason, $deleteRequest->unsubscribeNewsletter);

        $this->session->invalidateAllSessionsForUser($userId);

        return $this->respondOK();
    }

    #[OA\Put(summary: 'Sets a previously uploaded picture as the user\'s profile photo')]
    #[Route('users/current/photo', methods: ['PUT'])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success')]
    #[OA\Response(response: Response::HTTP_BAD_REQUEST, description: 'File does not exist or is not a valid upload')]
    #[OA\Response(response: Response::HTTP_UNAUTHORIZED, description: 'Not logged in')]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Not permitted')]
    public function setProfilePicture(#[MapRequestPayload] UUID $uuid): Response
    {
        $this->assertLoggedIn();

        // check if the photo exists and was uploaded by this user
        $uuidString = trim($uuid->uuid);
        if (!$this->uploadsPermissions->maySetUploadUsage($uuidString)) {
            throw new AccessDeniedHttpException('You do not have permission to use this file as profile photo');
        }

        $this->foodsaverTransactions->updatePhoto($this->session->id(), $uuidString);
        $this->session->refreshFromDatabase();

        return $this->respondOK(['uuid' => $uuid]);
    }

    #[OA\Delete(summary: 'Removes the user from the email bounce list')]
    #[Route('users/{userId}/email-bounce', methods: ['DELETE'], requirements: ['userId' => FsRequirement::USER_ID])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success')]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Not permitted')]
    #[OA\Response(response: Response::HTTP_UNAUTHORIZED, description: 'Not logged in')]
    public function removeFromBounceList(string $userId): Response
    {
        $this->assertLoggedIn();
        $userId = $this->resolveUserId($userId);
        if (!$this->profilePermissions->mayRemoveFromBounceList($userId)) {
            throw new AccessDeniedHttpException('Not permitted');
        }

        $this->profileTransactions->removeUserFromBounceList($userId);

        return $this->respondOK();
    }

    #[OA\Get(summary: 'Gets the names of multiple users by their IDs')]
    #[Route('users/{userIds}/names', methods: ['GET'], requirements: ['userIds' => FsRequirement::ID_LIST])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success.', content: new OA\JsonContent(type: 'array',
        items: new OA\Items(ref: new Model(type: CommonLabel::class))
    ))]
    public function getUserNames(string $userIds)
    {
        $userNames = $this->foodsaverGateway->getUserNames(explode(',', $userIds));
        if (!$this->session->id()) {
            // Abbreviate names when not logged in
            foreach ($userNames as &$user) {
                $user['name'] = mb_substr($user['name'], 0, 1) . '.';
            }
        }

        $userNames = array_map(fn ($user) => new CommonLabel($user['id'], $user['name']), $userNames);

        return $this->respondOK($userNames);
    }

    #[OA\Patch(summary: 'Updates the user profile information.')]
    #[Route('users/{userId}/profile', methods: ['PATCH'], requirements: ['userId' => FsRequirement::USER_ID])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success.', content: new Model(type: EditableProfileDTO::class))]
    #[OA\Response(response: Response::HTTP_UNAUTHORIZED, description: 'Not logged in.')]
    #[OA\Response(response: Response::HTTP_BAD_REQUEST, description: 'Bad Request.')]
    #[OA\Response(response: Response::HTTP_NOT_FOUND, description: 'User not found.')]
    public function patchUserProfile(string $userId, #[MapRequestPayload] EditableProfileDTO $editableProfileDTO): Response
    {
        $this->assertLoggedIn();
        $userId = $this->resolveUserId($userId);
        $editableProfileDTO->id = $userId;

        try {
            $this->settingsTransactions->patchProfile($userId, $editableProfileDTO);
        } catch (NotFoundHttpException) {
            throw new NotFoundHttpException('User not found.');
        }

        return $this->respondOK();
    }

    #[OA\Post(summary: 'Request a password reset by email')]
    #[Route('users/password-reset', methods: ['POST'])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success')]
    #[OA\Response(response: Response::HTTP_BAD_REQUEST, description: 'Invalid email address')]
    public function requestPasswordReset(#[MapRequestPayload] EmailAddress $email, Request $request, RateLimiterFactoryInterface $requestPasswordResetLimiter): Response
    {
        $this->checkRateLimit($request, $requestPasswordResetLimiter);

        $email = trim($email->email);
        if (!$this->emailHelper->validEmail($email)) {
            throw new BadRequestHttpException('Invalid email address');
        }

        $this->loginGateway->addPassRequest($email);

        // Always return success to prevent email enumeration attacks
        return $this->respondOK();
    }

    #[OA\Post(summary: 'Reset password using a reset token.')]
    #[Route('users/password-reset/confirmation', methods: ['POST'])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success')]
    #[OA\Response(response: Response::HTTP_BAD_REQUEST, description: 'Invalid or expired reset token, invalid password or invalid TOTP code')]
    public function resetPassword(#[MapRequestPayload] PasswordResetRequest $passwordResetRequest): Response
    {
        $resetToken = trim($passwordResetRequest->resetToken);
        if (!$this->loginGateway->checkResetKey($resetToken)) {
            throw new BadRequestHttpException('Invalid or expired reset token');
        }

        // Validate password
        $passwordValidationError = $this->loginGateway->checkPassword($passwordResetRequest->password);
        if ($passwordValidationError) {
            throw new BadRequestHttpException($passwordValidationError);
        }

        if (!$this->loginGateway->newPassword($passwordResetRequest)) {
            throw new BadRequestHttpException('Password reset failed');
        }

        return $this->respondOK();
    }

    #[OA\Get(summary: 'Validate a password reset token.')]
    #[Route('users/password-reset/validation', methods: ['GET'])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success', content: new OA\JsonContent(properties: [
        new OA\Property(property: 'isValid', type: 'boolean', description: 'Whether the reset token is valid')
    ]))]
    public function validateResetToken(#[MapQueryParameter] string $token): Response
    {
        return $this->respondOK(['isValid' => $this->loginGateway->checkResetKey(trim($token))]);
    }
}
