<?php

namespace Foodsharing\RestApi;

use Carbon\Carbon;
use Detection\MobileDetect;
use Exception;
use Foodsharing\Lib\Session;
use Foodsharing\Modules\Core\DBConstants\Foodsaver\Gender;
use Foodsharing\Modules\Core\DBConstants\Foodsaver\Role;
use Foodsharing\Modules\Foodsaver\FoodsaverGateway;
use Foodsharing\Modules\Foodsaver\FoodsaverTransactions;
use Foodsharing\Modules\Foodsaver\Profile;
use Foodsharing\Modules\Group\GroupTransactions;
use Foodsharing\Modules\Login\LoginGateway;
use Foodsharing\Modules\Profile\ProfileGateway;
use Foodsharing\Modules\Profile\ProfileTransactions;
use Foodsharing\Modules\Region\RegionGateway;
use Foodsharing\Modules\Region\RegionTransactions;
use Foodsharing\Modules\Register\DTO\RegisterData;
use Foodsharing\Modules\Register\RegisterTransactions;
use Foodsharing\Modules\Settings\SettingsGateway;
use Foodsharing\Modules\Unit\DTO\UserUnit;
use Foodsharing\Modules\Uploads\UploadsGateway;
use Foodsharing\Permissions\BlogPermissions;
use Foodsharing\Permissions\ContentPermissions;
use Foodsharing\Permissions\MailboxPermissions;
use Foodsharing\Permissions\NewsletterEmailPermissions;
use Foodsharing\Permissions\ProfilePermissions;
use Foodsharing\Permissions\QuizPermissions;
use Foodsharing\Permissions\RegionPermissions;
use Foodsharing\Permissions\ReportPermissions;
use Foodsharing\Permissions\StorePermissions;
use Foodsharing\RestApi\Models\Group\UserGroupModel;
use Foodsharing\RestApi\Models\Region\UserRegionModel;
use Foodsharing\Utility\DataHelper;
use Foodsharing\Utility\EmailHelper;
use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\Request\ParamFetcher;
use Nelmio\ApiDocBundle\Annotation\Model;
use OpenApi\Annotations as OA;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactory;

class UserRestController extends FoodsharingRestController
{
    private const MIN_RATING_MESSAGE_LENGTH = 100;
    private const MIN_PASSWORD_LENGTH = 8;
    private const MIN_AGE_YEARS = 18;
    private const DELETE_USER_MAX_REASON_LEN = 200;

    public function __construct(
        private Session $session,
        private LoginGateway $loginGateway,
        private FoodsaverGateway $foodsaverGateway,
        private ProfileGateway $profileGateway,
        private UploadsGateway $uploadsGateway,
        private RegionGateway $regionGateway,
        private EmailHelper $emailHelper,
        private RegisterTransactions $registerTransactions,
        private ProfileTransactions $profileTransactions,
        private FoodsaverTransactions $foodsaverTransactions,
        private SettingsGateway $settingsGateway,

        private ProfilePermissions $profilePermissions,
        private MailboxPermissions $mailboxPermissions,
        private QuizPermissions $quizPermissions,
        private ReportPermissions $reportPermissions,
        private StorePermissions $storePermissions,
        private ContentPermissions $contentPermissions,
        private BlogPermissions $blogPermissions,
        private RegionPermissions $regionPermissions,
        private NewsletterEmailPermissions $newsletterEmailPermissions,
        private RegionTransactions $regionTransactions,
        private GroupTransactions $groupTransactions,
        private DataHelper $dataHelper
    ) {
        $this->session = $session;
        $this->loginGateway = $loginGateway;
        $this->foodsaverGateway = $foodsaverGateway;
        $this->profileGateway = $profileGateway;
        $this->uploadsGateway = $uploadsGateway;
        $this->regionGateway = $regionGateway;
        $this->emailHelper = $emailHelper;
        $this->registerTransactions = $registerTransactions;
        $this->profileTransactions = $profileTransactions;
        $this->foodsaverTransactions = $foodsaverTransactions;
        $this->settingsGateway = $settingsGateway;
        $this->regionTransactions = $regionTransactions;
        $this->groupTransactions = $groupTransactions;

        $this->profilePermissions = $profilePermissions;
        $this->mailboxPermissions = $mailboxPermissions;
        $this->quizPermissions = $quizPermissions;
        $this->reportPermissions = $reportPermissions;
        $this->storePermissions = $storePermissions;
        $this->contentPermissions = $contentPermissions;
        $this->blogPermissions = $blogPermissions;
        $this->regionPermissions = $regionPermissions;
        $this->newsletterEmailPermissions = $newsletterEmailPermissions;
        $this->dataHelper = $dataHelper;
    }

    /**
     * Checks if the user is logged in and lists the basic user information. Returns 200 and the user data, 404 if the
     * user does not exist, or 401 if not logged in.
     *
     * @OA\Response(
     * 		response="200",
     * 		description="Success",
     *      @Model(type=Profile::class)
     * )
     * @OA\Response(response="401", description="Not logged in")
     * @OA\Response(response="404", description="User with that id not found")
     * @OA\Tag(name="user")
     */
    #[Rest\Get('user/{id}', requirements: ['id' => '\d+'])]
    public function user(int $id): Response
    {
        if (!$this->session->mayRole()) {
            throw new UnauthorizedHttpException('');
        }

        $data = $this->foodsaverGateway->getProfile($id);
        if (empty($data)) {
            throw new NotFoundHttpException('User does not exist.');
        }

        return $this->handleView($this->view($data, 200));
    }

    /**
     * Checks if the user is logged in  and lists the basic user information. Returns 401 if not logged in or 200 and
     * the user data.
     *
     * @OA\Tag(name="user")
     */
    #[Rest\Get('user/current')]
    public function currentUser(): Response
    {
        if (!$this->session->mayRole()) {
            throw new UnauthorizedHttpException('');
        }

        return $this->user($this->session->id());
    }

    /**
     * Normalizes the detailed profile of a user.
     *
     * @param array $data user profile data
     */
    private function normalizeUserDetails(array $data): array
    {
        $loggedIn = $this->session->mayRole();
        $mayEditUserProfile = $this->profilePermissions->mayEditUserProfile($data['id']);
        $mayAdministrateUserProfile = $this->profilePermissions->mayAdministrateUserProfile($data['id'], $data['bezirk_id']);

        $response = [];
        $response['id'] = $data['id'];
        $response['foodsaver'] = ($this->session->mayRole(Role::FOODSAVER)) ? true : false;
        $response['isVerified'] = ($data['verified'] === 1) ? true : false;
        $response['regionId'] = $data['bezirk_id'];
        $response['isSleeping'] = $this->dataHelper->parseSleepingState($data['sleep_status'], $data['sleep_from'], $data['sleep_until']);
        $response['regionName'] = ($data['bezirk_id'] === null) ? null : $this->regionGateway->getRegionName($data['bezirk_id']);
        $response['aboutMePublic'] = $data['about_me_public'];

        if ($loggedIn) {
            $infos = $this->foodsaverGateway->getFoodsaverBasics($data['id']);

            $response['mailboxId'] = $data['mailbox_id'];
            $response['hasCalendarToken'] = $this->settingsGateway->getApiToken($data['id']) !== null;
            $response['firstname'] = $data['name'];
            $response['lastname'] = $data['nachname'];
            $response['gender'] = $data['geschlecht'];
            $response['photo'] = $data['photo'];
            $response['sleeping'] = boolval($data['sleep_status']);
            $response['homepage'] = $data['homepage'];

            $response['stats']['weight'] = floatval($infos['stat_fetchweight']);
            $response['stats']['count'] = $infos['stat_fetchcount'];

            $response['permissions'] = [
                'mayEditUserProfile' => $mayEditUserProfile,
                'mayAdministrateUserProfile' => $mayAdministrateUserProfile,
                'administrateBlog' => $this->blogPermissions->mayAdministrateBlog(),
                'editQuiz' => $this->quizPermissions->mayEditQuiz(),
                'handleReports' => $this->reportPermissions->mayHandleReports(),
                'addStore' => $this->storePermissions->mayCreateStore(),
                'manageMailboxes' => $this->mailboxPermissions->mayManageMailboxes(),
                'editContent' => $this->contentPermissions->mayEditContent(),
                'administrateNewsletterEmail' => $this->newsletterEmailPermissions->mayAdministrateNewsletterEmail(),
                'administrateRegions' => $this->regionPermissions->mayAdministrateRegions(),
            ];
        } else {
            $response['firstname'] = ($data['name'] === null) ? null : $data['name'][0]; // Only return first character
        }

        if ($mayEditUserProfile) {
            $response['coordinates'] = [
                'lat' => floatval($data['lat']),
                'lon' => floatval($data['lon'])
            ];
            $response['address'] = $data['anschrift'];
            $response['city'] = $data['stadt'];
            $response['postcode'] = $data['plz'];
            $response['email'] = $data['email'];
            $response['landline'] = $data['telefon'];
            $response['mobile'] = $data['handy'];
            $response['birthday'] = $data['geb_datum'];
            $response['aboutMeIntern'] = $data['about_me_intern'];

            // load region
            $regions = $this->regionTransactions->getUserRegions($data['id']);
            $response['regions'] = array_map(fn (UserUnit $region): UserRegionModel => UserRegionModel::createFrom($region), $regions);

            // load groups
            $groups = $this->groupTransactions->getUserGroups($data['id']);
            $response['groups'] = array_map(fn (UserUnit $group): UserGroupModel => UserGroupModel::createFrom($group), $groups);
        }

        if ($mayAdministrateUserProfile) {
            $response['role'] = $data['rolle'];
            $response['position'] = $data['position'];
        }

        return $response;
    }

    /**
     * Lists the detailed profile of a user. Only returns basic information if not logged inor 200 and the data.
     *
     * @OA\Tag(name="user")
     * @Rest\Get("user/{id}/details", requirements={"id" = "\d+"})
     */
    /*
     * TODO: disabled because the following points need to be fixed.
     * - It uses normalizeUserDetails to return the same data as /user/current/details, including some private data like
     *   the calendar token. We need to find out which of the response is actually needed in the frontend by whom.
     * - The response includes permissions for the logged in user which does not make sense in an endpoint that should
     *   return details about another user.
     */
    /* public function userDetailsAction(int $id): Response
        {
            $data = $this->profileGateway->getData($id, -1, $this->reportPermissions->mayHandleReports());
            if (empty($data)) {
                throw new NotFoundHttpException('User does not exist.');
            }

            $normalisedData = $this->normalizeUserDetails($data);

            return $this->handleView($this->view($normalisedData, Response::HTTP_OK));
        } */
    /**
     * Lists the detailed profile of the current user. Returns 401 if not logged in or 200 and the data.
     *
     * @OA\Tag(name="user")
     */
    #[Rest\Get('user/current/details')]
    public function currentUserDetails(): Response
    {
        if (!$this->session->mayRole()) {
            throw new UnauthorizedHttpException('');
        }

        $data = $this->profileGateway->getData($this->session->id(), -1, $this->reportPermissions->mayHandleReports());
        $normalisedData = $this->normalizeUserDetails($data);

        return $this->handleView($this->view($normalisedData, Response::HTTP_OK));
    }

    /**
     * @OA\Tag(name="user")
     */
    #[Rest\Post('user/login')]
    #[Rest\RequestParam(name: 'email')]
    #[Rest\RequestParam(name: 'password')]
    #[Rest\RequestParam(name: 'remember_me', default: false)]
    public function login(ParamFetcher $paramFetcher, Request $request, RateLimiterFactory $loginLimiter): Response
    {
        $this->checkRateLimit($request, $loginLimiter);

        $email = $paramFetcher->get('email');
        $password = $paramFetcher->get('password');
        $rememberMe = (bool)$paramFetcher->get('remember_me');
        $fs_id = $this->loginGateway->login($email, $password);
        if ($fs_id) {
            $this->session->login($fs_id, $rememberMe);

            $mobdet = new MobileDetect();
            if ($mobdet->isMobile()) {
                $_SESSION['mob'] = 1;
            }

            // retrieve user data and normalise it
            $user = $this->foodsaverGateway->getProfile($fs_id);
            if (empty($user)) {
                throw new NotFoundHttpException('User does not exist.');
            }

            return $this->handleView($this->view($user, 200));
        }

        throw new UnauthorizedHttpException('', 'email or password are invalid');
    }

    /**
     * @OA\Tag(name="user")
     */
    #[Rest\Post('user/logout')]
    public function logout(): Response
    {
        $this->session->logout();

        return $this->handleView($this->view([], 200));
    }

    /**
     * Tests if an email address is valid for registration. Returns 400 if the parameter is not an email address or 200
     * and a 'valid' parameter that indicates if the email address can be used for registration.
     *
     * @OA\Tag(name="user")
     */
    #[Rest\Post('user/isvalidemail')]
    #[Rest\RequestParam(name: 'email', nullable: false)]
    public function testRegisterEmail(ParamFetcher $paramFetcher): Response
    {
        $email = $paramFetcher->get('email');
        if (
            empty($email)
            || !$this->emailHelper->validEmail($email)
            || $this->foodsaverGateway->emailDomainIsBlacklisted($email)
        ) {
            throw new BadRequestHttpException('email is not valid');
        }

        return $this->handleView($this->view([
            'valid' => $this->isEmailValidForRegistration($email)
        ], 200));
    }

    /**
     * Registers a new user.
     *
     * @OA\Tag(name="user")
     */
    #[Rest\Post('user')]
    #[Rest\RequestParam(name: 'firstname', nullable: false)]
    #[Rest\RequestParam(name: 'lastname', nullable: false)]
    #[Rest\RequestParam(name: 'email', nullable: false)]
    #[Rest\RequestParam(name: 'password', nullable: false)]
    #[Rest\RequestParam(name: 'gender', nullable: false, requirements: '\d+')]
    #[Rest\RequestParam(name: 'birthdate', nullable: false)]
    #[Rest\RequestParam(name: 'mobilePhone', nullable: true)]
    #[Rest\RequestParam(name: 'subscribeNewsletter', requirements: '(0|1)', default: 0)]
    public function registerUser(ParamFetcher $paramFetcher): Response
    {
        // validate data
        $data = new RegisterData();
        $data->firstName = trim(strip_tags($paramFetcher->get('firstname')));
        $data->lastName = trim(strip_tags($paramFetcher->get('lastname')));
        if (empty($data->firstName) || empty($data->lastName)) {
            throw new BadRequestHttpException('names must not be empty');
        }

        $data->email = trim($paramFetcher->get('email'));
        if (
            empty($data->email) || !$this->emailHelper->validEmail($data->email)
            || !$this->isEmailValidForRegistration($data->email)
            || $this->foodsaverGateway->emailDomainIsBlacklisted($data->email)
        ) {
            throw new BadRequestHttpException('email is not valid or already used');
        }

        $data->password = trim($paramFetcher->get('password'));
        if (strlen($data->password) < self::MIN_PASSWORD_LENGTH) {
            throw new BadRequestHttpException('password is too short');
        }

        $data->gender = (int)$paramFetcher->get('gender');
        if (!Gender::isValid($data->gender)) {
            $data->gender = Gender::NOT_SELECTED;
        }

        $birthdate = Carbon::createFromFormat('Y-m-d', $paramFetcher->get('birthdate'));
        if (empty($birthdate)) {
            throw new BadRequestHttpException('invalid birthdate');
        }
        $minBirthdate = Carbon::today()->subYears(self::MIN_AGE_YEARS);
        if ($birthdate > $minBirthdate) {
            throw new BadRequestHttpException('you are not old enough');
        }
        $data->birthday = $birthdate;

        $data->mobilePhone = strip_tags($paramFetcher->get('mobilePhone') ?? '');
        $data->subscribeNewsletter = (int)$paramFetcher->get('subscribeNewsletter') == 1;

        try {
            // register user and send out registration email
            $id = $this->registerTransactions->registerUser($data);

            // return the created user
            $user = $this->foodsaverGateway->getProfile($id);

            return $this->handleView($this->view($user, 200));
        } catch (Exception $e) {
            throw new HttpException(500, 'could not register user', $e);
        }
    }

    private function isEmailValidForRegistration(string $email): bool
    {
        return !$this->emailHelper->isFoodsharingEmailAddress($email)
            && !$this->foodsaverGateway->emailExists($email);
    }

    /**
     * @OA\Tag(name="user")
     */
    #[Rest\Delete('user/{userId}', requirements: ['userId' => '\d+'])]
    #[Rest\RequestParam(name: 'reason', nullable: true, default: '')]
    public function deleteUser(int $userId, ParamFetcher $paramFetcher): Response
    {
        if (!$this->session->id()) {
            throw new UnauthorizedHttpException('');
        }
        if (!$this->profilePermissions->mayDeleteUser($userId)) {
            throw new AccessDeniedHttpException();
        }

        $reason = trim($paramFetcher->get('reason'));
        if (strlen($reason) > self::DELETE_USER_MAX_REASON_LEN) {
            throw new BadRequestHttpException('reason text is too long: must be at most ' . self::DELETE_USER_MAX_REASON_LEN . ' characters');
        }

        // needs the session ID, so we can't log out just yet
        $this->foodsaverTransactions->deleteFoodsaver($userId, $reason);

        if ($userId === $this->session->id()) {
            $this->session->logout();
        }

        return $this->handleView($this->view());
    }

    /**
     * Gives a banana to a user.
     *
     * @OA\Parameter(name="userId", in="path", @OA\Schema(type="integer"), description="to which user to give the banana")
     * @OA\RequestBody(description="message to the user")
     * @OA\Response(response="200", description="Success.")
     * @OA\Response(response="400", description="Accompanying message is too short.")
     * @OA\Response(response="403", description="Insufficient permissions to rate that user.")
     * @OA\Response(response="404", description="User to rate does not exist.")
     * @OA\Tag(name="user")
     */
    #[Rest\Put('user/{userId}/banana', requirements: ['userId' => '\d+'])]
    #[Rest\RequestParam(name: 'message', nullable: false)]
    public function addBanana(int $userId, ParamFetcher $paramFetcher): Response
    {
        if (!$this->session->id()) {
            throw new UnauthorizedHttpException('');
        }
        // make sure that users may not give themselves bananas
        if ($this->session->id() === $userId) {
            throw new AccessDeniedHttpException();
        }

        // check if the user exists
        if (!$this->foodsaverGateway->foodsaverExists($userId)) {
            throw new NotFoundHttpException();
        }

        // do not allow giving bananas twice
        if ($this->profileGateway->hasGivenBanana($this->session->id(), $userId)) {
            throw new AccessDeniedHttpException();
        }

        // check length of message
        $message = trim($paramFetcher->get('message'));
        if (strlen($message) < self::MIN_RATING_MESSAGE_LENGTH) {
            throw new BadRequestHttpException('text too short: ' . strlen($message) . ' < ' . self::MIN_RATING_MESSAGE_LENGTH);
        }

        $this->profileTransactions->giveBanana($userId, $message, $this->session->id());

        return $this->handleView($this->view([], 200));
    }

    /**
     * Deletes a banana.
     *
     * @OA\Parameter(name="userId", in="path", @OA\Schema(type="integer"), description="the owner of the banana")
     * @OA\Parameter(name="senderId", in="path", @OA\Schema(type="integer"), description="the sender of the banana")
     * @OA\Response(response="200", description="Success.")
     * @OA\Response(response="401", description="Not logged in.")
     * @OA\Response(response="403", description="Insufficient permissions to delete that banana.")
     * @OA\Response(response="404", description="Banana does not exist.")
     * @OA\Tag(name="user")
     */
    #[Rest\Delete('user/{userId}/banana/{senderId}', requirements: ['userId' => '\d+'])]
    public function deleteBanana(int $userId, int $senderId): Response
    {
        if (!$this->session->mayRole()) {
            throw new UnauthorizedHttpException('');
        }

        if (!$this->profilePermissions->mayDeleteBanana($userId)) {
            throw new AccessDeniedHttpException();
        }

        $isDeleted = $this->profileGateway->removeBanana($userId, $senderId);

        return $this->handleView($this->view([], $isDeleted ? 200 : 404));
    }

    /**
     * Sets a previously uploaded picture as the user's profile photo.
     *
     * @OA\RequestBody(description="UUID of the previously uploaded file")
     * @OA\Response(response="200", description="Success.")
     * @OA\Response(response="400", description="File does not exist.")
     * @OA\Response(response="401", description="Not logged in.")
     * @OA\Response(response="403", description="File was not uploaded by this user.")
     * @OA\Tag(name="user")
     */
    #[Rest\Patch('user/photo')]
    #[Rest\RequestParam(name: 'uuid', nullable: false)]
    public function setProfilePicture(ParamFetcher $paramFetcher): Response
    {
        $userId = $this->session->id();
        if (!$userId) {
            throw new UnauthorizedHttpException('');
        }

        // check if the photo exists and was uploaded by this user
        $uuid = trim($paramFetcher->get('uuid'));
        try {
            if ($this->uploadsGateway->getUser($uuid) !== $userId) {
                throw new AccessDeniedHttpException();
            }
        } catch (Exception $e) {
            throw new BadRequestHttpException();
        }

        $this->foodsaverGateway->updatePhoto($this->session->id(), '/api/uploads/' . $uuid);
        $this->session->refreshFromDatabase();

        return $this->handleView($this->view([], 200));
    }

    /**
     * Removes the user from the email bounce list. This will have no effect and return 200 if the user was
     * not on the bounce list.
     *
     * @OA\Parameter(name="userId", in="path", @OA\Schema(type="integer"), description="which user to remove from the list")
     * @OA\Response(response="200", description="Success")
     * @OA\Response(response="403", description="Insufficient permissions")
     * @OA\Tag(name="user")
     */
    #[Rest\Delete('user/{userId}/emailbounce', requirements: ['userId' => '\d+'])]
    public function removeFromBounceList(int $userId): Response
    {
        if (!$this->session->id()) {
            throw new UnauthorizedHttpException('');
        }
        if (!$this->profilePermissions->mayRemoveFromBounceList($userId)) {
            throw new AccessDeniedHttpException();
        }

        $this->profileTransactions->removeUserFromBounceList($userId);

        return $this->handleView($this->view([], 200));
    }
}
