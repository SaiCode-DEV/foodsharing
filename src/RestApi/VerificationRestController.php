<?php

namespace Foodsharing\RestApi;

use Foodsharing\Lib\Session;
use Foodsharing\Modules\Bell\BellGateway;
use Foodsharing\Modules\Bell\DTO\Bell;
use Foodsharing\Modules\Core\DBConstants\Bell\BellType;
use Foodsharing\Modules\Foodsaver\FoodsaverGateway;
use Foodsharing\Modules\PassportGenerator\PassportGeneratorTransaction;
use Foodsharing\Modules\Profile\DTO\PassHistoryEntry;
use Foodsharing\Modules\Profile\DTO\VerificationHistoryEntry;
use Foodsharing\Modules\Profile\ProfileGateway;
use Foodsharing\Modules\Store\PickupGateway;
use Foodsharing\Permissions\PassportPermissions;
use Foodsharing\Permissions\ProfilePermissions;
use Foodsharing\RestApi\Models\Passport\CreateRegionPassportModel;
use Foodsharing\Utility\EmailHelper;
use FOS\RestBundle\Controller\Annotations as Rest;
use Nelmio\ApiDocBundle\Annotation\Model;
use OpenApi\Annotations as OA;
use OpenApi\Attributes as OA2;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class VerificationRestController extends AbstractFoodsharingRestController
{
    private readonly BellGateway $bellGateway;
    private readonly FoodsaverGateway $foodsaverGateway;
    private readonly ProfileGateway $profileGateway;
    private readonly PickupGateway $pickupGateway;
    private readonly ProfilePermissions $profilePermissions;
    private readonly EmailHelper $emailHelper;
    protected TranslatorInterface $translator;
    private readonly PassportPermissions $passportPermissions;
    private readonly PassportGeneratorTransaction $passportGeneratorTransaction;
    protected Session $session;

    public function __construct(
        BellGateway $bellGateway,
        FoodsaverGateway $foodsaverGateway,
        ProfileGateway $profileGateway,
        PickupGateway $pickupGateway,
        ProfilePermissions $profilePermissions,
        EmailHelper $emailHelper,
        TranslatorInterface $translator,
        PassportPermissions $passportPermissions,
        PassportGeneratorTransaction $passportGeneratorTransaction,
        Session $session,
    ) {
        $this->bellGateway = $bellGateway;
        $this->foodsaverGateway = $foodsaverGateway;
        $this->profileGateway = $profileGateway;
        $this->pickupGateway = $pickupGateway;
        $this->profilePermissions = $profilePermissions;
        $this->emailHelper = $emailHelper;
        $this->translator = $translator;
        $this->passportPermissions = $passportPermissions;
        $this->passportGeneratorTransaction = $passportGeneratorTransaction;
        $this->session = $session;
    }

    /**
     * Changes verification status of one user to 'verified'.
     *
     * @OA\Parameter(name="userId", in="path", @OA\Schema(type="integer"), description="which user to verify")
     * @OA\Response(response="200", description="Success.")
     * @OA\Response(response="401", description="Not logged in.")
     * @OA\Response(response="403", description="Insufficient permissions to verify this user.")
     * @OA\Response(response="404", description="User not found.")
     * @OA\Response(response="422", description="Already verified.")
     * @OA\Tag(name="verification")
     */
    #[Rest\Patch('user/{userId}/verification', requirements: ['userId' => '\d+'])]
    public function verifyUser(int $userId): Response
    {
        $sessionId = $this->session->id();
        if (!$sessionId) {
            throw new UnauthorizedHttpException('');
        }

        if (!$this->profilePermissions->mayChangeUserVerification($userId)) {
            throw new AccessDeniedHttpException();
        }

        if ($this->profileGateway->isUserVerified($userId)) {
            throw new UnprocessableEntityHttpException('User is already verified');
        }

        $this->foodsaverGateway->changeUserVerification($userId, $sessionId, true);
        $this->bellGateway->delBellsByIdentifier(BellType::createIdentifier(BellType::NEW_FOODSAVER_IN_REGION, $userId));

        $passportGenLink = '/?page=settings&sub=passport';
        $bellData = Bell::create(
            'foodsaver_verified_title',
            'foodsaver_verified',
            'fas fa-camera',
            ['href' => $passportGenLink],
            ['user' => $this->session->user('name')],
            BellType::createIdentifier(BellType::FOODSAVER_VERIFIED, $userId)
        );
        $this->bellGateway->addBell($userId, $bellData);

        $passportMailLink = 'https://foodsharing.de' . $passportGenLink;
        $fs = $this->foodsaverGateway->getFoodsaver($userId);
        $this->emailHelper->tplMail('user/verification', $fs['email'], [
            'name' => $fs['name'],
            'link' => $passportMailLink,
            'anrede' => $this->translator->trans('salutation.' . $fs['geschlecht']),
        ], false, true);

        return $this->handleView($this->view([], 200));
    }

    /**
     * Changes verification status of one user to 'deverified'.
     *
     * @OA\Parameter(name="userId", in="path", @OA\Schema(type="integer"), description="which user to deverify")
     * @OA\Response(response="200", description="Success.")
     * @OA\Response(response="400", description="Has future pickups.")
     * @OA\Response(response="401", description="Not logged in.")
     * @OA\Response(response="403", description="Insufficient permissions to deverify this user.")
     * @OA\Response(response="404", description="User not found.")
     * @OA\Response(response="422", description="Already deverified.")
     * @OA\Tag(name="verification")
     */
    #[Rest\Delete('user/{userId}/verification', requirements: ['userId' => '\d+'])]
    public function deverifyUser(int $userId): Response
    {
        $sessionId = $this->session->id();
        if (!$sessionId) {
            throw new UnauthorizedHttpException('');
        }

        if (!$this->profilePermissions->mayChangeUserVerification($userId)) {
            throw new AccessDeniedHttpException();
        }

        if (!$this->profileGateway->isUserVerified($userId)) {
            throw new UnprocessableEntityHttpException('User is already deverified');
        }

        $hasPlannedPickups = $this->pickupGateway->getNextPickups($userId, 1);
        if ($hasPlannedPickups) {
            throw new BadRequestHttpException('This user must not be signed up for any future pickups.');
        }

        $this->foodsaverGateway->changeUserVerification($userId, $sessionId, false);

        return $this->handleView($this->view([], 200));
    }

    /**
     * Returns a list of the user's past (de-)verifications.
     *
     * @OA\Parameter(name="userId", in="path", @OA\Schema(type="integer"), description="which user's history to return")
     * @OA\Response(response="200", description="Success.", @OA\JsonContent(type="array",
     *     @OA\Items(ref=@Model(type=VerificationHistoryEntry::class))
     * ))
     * @OA\Response(response="401", description="Not logged in.")
     * @OA\Response(response="403", description="Insufficient permissions to view this user's history.")
     * @OA\Tag(name="verification")
     */
    #[Rest\Get('user/{userId}/verificationhistory', requirements: ['userId' => '\d+'])]
    public function getVerificationHistory(int $userId): Response
    {
        $viewerId = $this->session->id();
        if (!$viewerId) {
            throw new UnauthorizedHttpException('');
        }
        if (!$this->profilePermissions->maySeeHistory($userId)) {
            throw new AccessDeniedHttpException();
        }

        $history = $this->profileGateway->getVerifyHistory($userId);

        return $this->handleView($this->view($history, 200));
    }

    /**
     * Returns a list of the times the user's pass was created.
     *
     * @OA\Parameter(name="userId", in="path", @OA\Schema(type="integer"), description="which user's history to return")
     * @OA\Response(response="200", description="Success.", @OA\JsonContent(type="array",
     *     @OA\Items(ref=@Model(type=PassHistoryEntry::class))
     * ))
     * @OA\Response(response="401", description="Not logged in.")
     * @OA\Response(response="403", description="Insufficient permissions to view this user's history.")
     * @OA\Tag(name="verification")
     */
    #[Rest\Get('user/{userId}/passhistory', requirements: ['userId' => '\d+'])]
    public function getPassHistory(int $userId): Response
    {
        $viewerId = $this->session->id();
        if (!$viewerId) {
            throw new UnauthorizedHttpException('');
        }
        if (!$this->profilePermissions->maySeeHistory($userId)) {
            throw new AccessDeniedHttpException();
        }

        $history = $this->profileGateway->getPassHistory($userId);

        return $this->handleView($this->view($history, 200));
    }

    #[OA2\Post(summary: 'User can create own passport')]
    #[OA2\Response(
        response: 200,
        description: 'Success.',
        content: new OA2\MediaType(
            mediaType: 'application/pdf',
            schema: new OA2\Schema(
                description: 'Passport as PDF-File',
                type: 'string',
                format: 'binary'
            )
        )
    )]
    #[OA2\Response(response: 401, description: 'Not logged in.')]
    #[OA2\Response(response: 403, description: 'Insufficient permissions to create own passport.')]
    #[OA2\Tag(name: 'verification')]
    #[Rest\Post('user/current/passport')]
    public function createAsUser(): Response
    {
        $sessionId = $this->session->id();
        if (!$sessionId) {
            throw new UnauthorizedHttpException('');
        }

        if (!$this->passportPermissions->mayCreatePassportAsUser($sessionId)) {
            throw new AccessDeniedHttpException();
        }

        $passDate = $this->passportGeneratorTransaction->getPassDate($sessionId);

        $pdf = $this->passportGeneratorTransaction->generate([$sessionId], $passDate, false, true);

        $response = new Response($pdf);
        $response->headers->set('Content-Type', 'application/pdf');

        return $response;
    }

    #[OA2\Post(summary: 'Ambassador can create passports for users in region')]
    #[OA2\Response(
        response: 200,
        description: 'Success.',
        content: new OA2\MediaType(
            mediaType: 'application/pdf',
            schema: new OA2\Schema(
                description: 'Passport as PDF-File',
                type: 'string',
                format: 'binary'
            )
        )
    )]
    #[OA2\Response(response: 401, description: 'Not logged in.')]
    #[OA2\Response(response: 403, description: 'Insufficient permissions to create passport as ambassador in region.')]
    #[OA2\Response(response: 404, description: 'User not found.')]
    #[OA2\Tag(name: 'verification')]
    #[Rest\Post('region/{regionId}/passport')]
    #[ParamConverter('regionPassportModel', converter: 'fos_rest.request_body')]
    public function createAsAmbassador(int $regionId, CreateRegionPassportModel $regionPassportModel, ValidatorInterface $validator): Response
    {
        $sessionId = $this->session->id();
        if (!$sessionId) {
            throw new UnauthorizedHttpException('');
        }

        if (!$this->passportPermissions->mayCreatePassportAsAmbassador($sessionId, $regionId)) {
            throw new AccessDeniedHttpException();
        }

        $this->assertThereAreNoValidationErrors($validator, $regionPassportModel);

        $areUsersInRegion = $this->passportGeneratorTransaction->areUsersInRegion($regionPassportModel->userIds, $regionId);
        if (!$areUsersInRegion->result) {
            throw new NotFoundHttpException($areUsersInRegion->message);
        }

        try {
            $pdf = $this->passportGeneratorTransaction->generate($regionPassportModel->userIds, null, true, false, true);

            $response = new Response($pdf);
            $response->headers->set('Content-Type', 'application/pdf');
        } catch (\Exception $ex) {
            throw new BadRequestException($ex->getMessage());
        }

        return $response;
    }
}
