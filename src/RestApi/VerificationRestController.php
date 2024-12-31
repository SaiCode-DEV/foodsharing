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
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

#[OA\Tag(name: 'verification')]
class VerificationRestController extends AbstractFoodsharingRestController
{
    public function __construct(
        private readonly BellGateway $bellGateway,
        private readonly FoodsaverGateway $foodsaverGateway,
        private readonly ProfileGateway $profileGateway,
        private readonly PickupGateway $pickupGateway,
        private readonly ProfilePermissions $profilePermissions,
        private readonly EmailHelper $emailHelper,
        protected TranslatorInterface $translator,
        private readonly PassportPermissions $passportPermissions,
        private readonly PassportGeneratorTransaction $passportGeneratorTransaction,
        protected Session $session,
    ) {
        parent::__construct($session);
    }

    #[OA\Patch(summary: 'Changes verification status of one user to verified')]
    #[OA\Parameter(
        name: 'userId',
        in: 'path',
        schema: new OA\Schema(type: 'integer'),
        description: 'which user to verify'
    )]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success.')]
    #[OA\Response(response: Response::HTTP_UNAUTHORIZED, description: 'Not logged in.')]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Insufficient permissions to verify this user.')]
    #[OA\Response(response: Response::HTTP_NOT_FOUND, description: 'User not found.')]
    #[OA\Response(response: Response::HTTP_UNPROCESSABLE_ENTITY, description: 'Already verified.')]
    #[Route(
        path: '/user/{userId}/verification',
        methods: ['PATCH'],
        requirements: ['userId' => '\d+']
    )]
    public function verifyUser(int $userId): Response
    {
        $this->assertLoggedIn();

        if (!$this->profilePermissions->mayChangeUserVerification($userId)) {
            throw new AccessDeniedHttpException();
        }

        if ($this->profileGateway->isUserVerified($userId)) {
            throw new UnprocessableEntityHttpException('User is already verified');
        }

        $this->foodsaverGateway->changeUserVerification($userId, $this->session->id(), true);
        $this->bellGateway->delBellsByIdentifier(BellType::createIdentifier(BellType::NEW_FOODSAVER_IN_REGION, $userId));

        $bellData = Bell::create(
            'foodsaver_verified_title',
            'foodsaver_verified',
            'fas fa-camera',
            ['href' => null],
            ['user' => $this->session->user('name')],
            BellType::createIdentifier(BellType::FOODSAVER_VERIFIED, $userId)
        );

        $this->bellGateway->addBell($userId, $bellData);

        $fs = $this->foodsaverGateway->getFoodsaver($userId);
        $this->emailHelper->tplMail('user/verification', $fs['email'], [
            'name' => $fs['name'],
            'anrede' => $this->translator->trans('salutation.' . $fs['geschlecht']),
        ], false, true);

        return $this->respondOK();
    }

    #[OA\Delete(
        path: '/user/{userId}/verification',
        summary: 'Changes verification status of one user to deverified'
    )]
    #[OA\Parameter(
        name: 'userId',
        in: 'path',
        schema: new OA\Schema(type: 'integer'),
        description: 'which user to deverify'
    )]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success.')]
    #[OA\Response(response: Response::HTTP_BAD_REQUEST, description: 'Has future pickups.')]
    #[OA\Response(response: Response::HTTP_UNAUTHORIZED, description: 'Not logged in.')]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Insufficient permissions to deverify this user.')]
    #[OA\Response(response: Response::HTTP_NOT_FOUND, description: 'User not found.')]
    #[OA\Response(response: Response::HTTP_UNPROCESSABLE_ENTITY, description: 'Already deverified.')]
    #[Route(
        path: '/user/{userId}/verification',
        methods: ['DELETE'],
        requirements: ['userId' => '\d+']
    )]
    public function deverifyUser(int $userId): Response
    {
        $this->assertLoggedIn();

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

        $this->foodsaverGateway->changeUserVerification($userId, $this->session->id(), false);

        return $this->respondOK();
    }

    #[OA\Get(
        path: '/user/{userId}/verificationhistory',
        summary: 'Returns a list of the users past (de-)verifications'
    )]
    #[OA\Parameter(
        name: 'userId',
        in: 'path',
        schema: new OA\Schema(type: 'integer'),
        description: 'which users history to return'
    )]
    #[OA\Response(
        response: Response::HTTP_OK,
        description: 'Success.',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(type: 'object', ref: VerificationHistoryEntry::class)
        )
    )]
    #[OA\Response(response: Response::HTTP_UNAUTHORIZED, description: 'Not logged in.')]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Insufficient permissions to view this users history.')]
    #[Route(
        path: '/user/{userId}/verificationhistory',
        methods: ['GET'],
        requirements: ['userId' => '\d+']
    )]
    public function getVerificationHistory(int $userId): Response
    {
        $this->assertLoggedIn();

        if (!$this->profilePermissions->maySeeHistory($userId)) {
            throw new AccessDeniedHttpException();
        }

        $history = $this->profileGateway->getVerifyHistory($userId);

        return $this->respondOK($history);
    }

    #[OA\Get(
        path: '/user/{userId}/passhistory',
        summary: 'Returns a list of the times the users pass was created'
    )]
    #[OA\Parameter(
        name: 'userId',
        in: 'path',
        schema: new OA\Schema(type: 'integer'),
        description: 'which users history to return'
    )]
    #[OA\Response(
        response: Response::HTTP_OK,
        description: 'Success.',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(type: 'object', ref: PassHistoryEntry::class)
        )
    )]
    #[OA\Response(response: Response::HTTP_UNAUTHORIZED, description: 'Not logged in.')]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Insufficient permissions to view this users history.')]
    #[Route(
        path: '/user/{userId}/passhistory',
        methods: ['GET'],
        requirements: ['userId' => '\d+']
    )]
    public function getPassHistory(int $userId): Response
    {
        $this->assertLoggedIn();

        if (!$this->profilePermissions->maySeeHistory($userId)) {
            throw new AccessDeniedHttpException();
        }

        $history = $this->profileGateway->getPassHistory($userId);

        return $this->respondOK($history);
    }

    #[OA\Post(summary: 'User can create own passport')]
    #[OA\Response(
        response: Response::HTTP_OK,
        description: 'Success.',
        content: new OA\MediaType(
            mediaType: 'application/pdf',
            schema: new OA\Schema(
                description: 'Passport as PDF-File',
                type: 'string',
                format: 'binary'
            )
        )
    )]
    #[OA\Response(response: Response::HTTP_UNAUTHORIZED, description: 'Not logged in.')]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Insufficient permissions to create own passport.')]
    #[Route(
        path: '/user/current/passport',
        methods: ['POST']
    )]
    public function createAsUser(): Response
    {
        $this->assertLoggedIn();

        if (!$this->passportPermissions->mayCreatePassportAsUser($this->session->id())) {
            throw new AccessDeniedHttpException();
        }

        $pdf = $this->passportGeneratorTransaction->generatePassportAsUser($this->session->id());

        $response = new Response($pdf);
        $response->headers->set('Content-Type', 'application/pdf');

        return $response;
    }

    #[OA\Post(summary: 'Ambassador can create passports for users in region')]
    #[OA\Response(
        response: Response::HTTP_OK,
        description: 'Success.',
        content: new OA\MediaType(
            mediaType: 'application/pdf',
            schema: new OA\Schema(
                description: 'Passport as PDF-File',
                type: 'string',
                format: 'binary'
            )
        )
    )]
    #[OA\Response(response: Response::HTTP_UNAUTHORIZED, description: 'Not logged in.')]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Insufficient permissions to create passport as ambassador in region.')]
    #[OA\Response(response: Response::HTTP_NOT_FOUND, description: 'User not found.')]
    #[Route(
        path: '/region/{regionId}/passport',
        methods: ['POST']
    )]
    public function createAsAmbassador(
        int $regionId,
        #[MapRequestPayload] CreateRegionPassportModel $regionPassportModel
    ): Response {
        $this->assertLoggedIn();

        if (!$this->passportPermissions->mayCreatePassportAsAmbassador($this->session->id(), $regionId)) {
            throw new AccessDeniedHttpException();
        }

        $areUsersInRegion = $this->passportGeneratorTransaction->areUsersInRegion($regionPassportModel->userIds, $regionId);
        if (!$areUsersInRegion->result) {
            throw new NotFoundHttpException($areUsersInRegion->message);
        }

        try {
            $result = $this->passportGeneratorTransaction->generatePassportAsAmbassador($regionPassportModel);

            $response = new Response($result);
            if ($regionPassportModel->createPdf) {
                $response->headers->set('Content-Type', 'application/pdf');
            }
        } catch (\Exception $ex) {
            throw new BadRequestException($ex->getMessage());
        }

        return $response;
    }

    // Combined Wallet API
    // http://localhost:18080/api/user/current/{walletType}/wallet
    #[OA\Get(summary: 'Returns the wallet URL for the current user')]
    #[OA\Parameter(
        name: 'walletType',
        description: 'Type of wallet to generate (google or apple)',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'string', enum: ['google', 'apple'])
    )]
    #[OA\Response(response: Response::HTTP_UNAUTHORIZED, description: 'Not logged in.')]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Insufficient permissions to create own passport.')]
    #[OA\Response(response: Response::HTTP_NOT_FOUND, description: 'Invalid wallet type.')]
    #[Route(
        path: '/user/current/{walletType}/wallet',
        methods: ['GET']
    )]
    public function getWallet(string $walletType): Response
    {
        $userId = $this->session->id();
        if (!$userId || !$this->session->user('role')) {
            throw new UnauthorizedHttpException('Invalid Session, please login again');
        }
        if (!$this->passportPermissions->mayCreatePassportAsUser($userId)) {
            throw new AccessDeniedHttpException();
        }

        $allowedWallets = ['google', 'apple'];
        if (!in_array($walletType, $allowedWallets)) {
            throw new NotFoundHttpException('Invalid wallet type');
        }

        try {
            $res = $this->passportGeneratorTransaction->createWallet($userId, $walletType);
            if (empty($res)) {
                return $this->json(['error' => 'Could not generate wallet URL'], Response::HTTP_BAD_REQUEST);
            }

            return $this->json(['url' => $res]);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }
}
