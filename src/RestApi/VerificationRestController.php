<?php

namespace Foodsharing\RestApi;

use Foodsharing\Lib\Session;
use Foodsharing\Modules\Bell\BellGateway;
use Foodsharing\Modules\Bell\DTO\Bell;
use Foodsharing\Modules\Core\DBConstants\Bell\BellType;
use Foodsharing\Modules\Foodsaver\FoodsaverGateway;
use Foodsharing\Modules\Message\MessageTransactions;
use Foodsharing\Modules\PassportGenerator\PassportGeneratorTransaction;
use Foodsharing\Modules\Profile\DTO\PassHistoryEntry;
use Foodsharing\Modules\Profile\DTO\VerificationHistoryEntry;
use Foodsharing\Modules\Profile\ProfileGateway;
use Foodsharing\Modules\Store\PickupGateway;
use Foodsharing\Permissions\PassportPermissions;
use Foodsharing\Permissions\ProfilePermissions;
use Foodsharing\RestApi\DTO\OptionalMessage;
use Foodsharing\RestApi\Models\Passport\CreateRegionPassportModel;
use Foodsharing\Utility\EmailHelper;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Contracts\Translation\TranslatorInterface;

#[OA\Tag(name: 'verification')]
#[OA\Response(response: Response::HTTP_UNAUTHORIZED, description: 'Not logged in.')]
class VerificationRestController extends AbstractFoodsharingRestController
{
    public function __construct(
        private readonly BellGateway $bellGateway,
        private readonly FoodsaverGateway $foodsaverGateway,
        private readonly ProfileGateway $profileGateway,
        private readonly PickupGateway $pickupGateway,
        private readonly ProfilePermissions $profilePermissions,
        private readonly EmailHelper $emailHelper,
        private readonly TranslatorInterface $translator,
        private readonly PassportPermissions $passportPermissions,
        private readonly PassportGeneratorTransaction $passportGeneratorTransaction,
        private readonly MessageTransactions $messageTransactions,
        protected Session $session
    ) {
        parent::__construct($session);
    }

    #[OA\Post(summary: 'Changes verification status of one user to verified')]
    #[Route('/users/{userId}/verifications', methods: ['POST'], requirements: ['userId' => Requirement::POSITIVE_INT])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success.')]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Insufficient permissions to verify this user.')]
    #[OA\Response(response: Response::HTTP_NOT_FOUND, description: 'User not found.')]
    #[OA\Response(response: Response::HTTP_UNPROCESSABLE_ENTITY, description: 'Already verified.')]
    public function verifyUser(int $userId, #[MapRequestPayload] OptionalMessage $message): Response
    {
        $this->assertLoggedIn();

        if (!$this->profilePermissions->mayChangeUserVerification($userId)) {
            throw new AccessDeniedHttpException('Not permitted');
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
            ['href' => '/user/current/settings?sub=passport'],
            ['user' => $this->session->user('name')],
            BellType::createIdentifier(BellType::FOODSAVER_VERIFIED, $userId)
        );

        $this->bellGateway->addBellForUsers([$userId], $bellData);

        $fs = $this->foodsaverGateway->getFoodsaver($userId);
        $this->emailHelper->tplMail('user/verification', $fs['email'], [
            'name' => $fs['name'],
            'anrede' => $this->translator->trans('salutation.' . $fs['geschlecht']),
        ], false, true);

        $this->messageTransactions->sendRequiredMessageToUser($userId, $this->session->id(), 'verify', $message->message);

        return $this->respondOK();
    }

    #[OA\Delete(summary: 'Changes verification status of one user to deverified')]
    #[Route('/users/{userId}/verifications', methods: ['DELETE'], requirements: ['userId' => Requirement::POSITIVE_INT])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success.')]
    #[OA\Response(response: Response::HTTP_BAD_REQUEST, description: 'Has future pickups.')]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Insufficient permissions to deverify this user.')]
    #[OA\Response(response: Response::HTTP_NOT_FOUND, description: 'User not found.')]
    #[OA\Response(response: Response::HTTP_UNPROCESSABLE_ENTITY, description: 'Already deverified.')]
    public function deverifyUser(int $userId): Response
    {
        $this->assertLoggedIn();

        if (!$this->profilePermissions->mayChangeUserVerification($userId)) {
            throw new AccessDeniedHttpException('Not permitted');
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

    #[OA\Get(summary: 'Returns a users (de-)verification history')]
    #[Route('/users/{userId}/verifications', methods: ['GET'], requirements: ['userId' => Requirement::POSITIVE_INT])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success', content: new OA\JsonContent(type: 'array',
        items: new OA\Items(ref: new Model(type: VerificationHistoryEntry::class))
    ))]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Insufficient permissions to view this users history.')]
    public function getVerificationHistory(int $userId): Response
    {
        $this->assertLoggedIn();

        if (!$this->profilePermissions->maySeeHistory($userId)) {
            throw new AccessDeniedHttpException('Not permitted');
        }

        $history = $this->profileGateway->getVerifyHistory($userId);

        return $this->respondOK($history);
    }

    #[OA\Get(summary: 'Returns a users pass history')]
    #[Route('/users/{userId}/pass-history', methods: ['GET'], requirements: ['userId' => Requirement::POSITIVE_INT])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success', content: new OA\JsonContent(type: 'array',
        items: new OA\Items(ref: new Model(type: PassHistoryEntry::class))
    ))]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Insufficient permissions to view this users history.')]
    public function getPassHistory(int $userId): Response
    {
        $this->assertLoggedIn();

        if (!$this->profilePermissions->maySeeHistory($userId)) {
            throw new AccessDeniedHttpException('Not permitted');
        }

        $history = $this->profileGateway->getPassHistory($userId);

        return $this->respondOK($history);
    }

    #[OA\Get(summary: 'Returns the current users foodsaver passport')]
    #[Route('/users/current/passport', methods: ['GET'])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success', content: new OA\MediaType(mediaType: 'application/pdf',
        schema: new OA\Schema(description: 'Passport as PDF-File', type: 'string', format: 'binary')
    ))]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Insufficient permissions to create own passport.')]
    public function createAsUser(): Response
    {
        $this->assertLoggedIn();

        if (!$this->passportPermissions->mayCreatePassportAsUser($this->session->id())) {
            throw new AccessDeniedHttpException('Not permitted');
        }

        $pdf = $this->passportGeneratorTransaction->generatePassportAsUser($this->session->id());

        $response = new Response($pdf);
        $response->headers->set('Content-Type', 'application/pdf');

        return $response;
    }

    #[OA\Post(summary: 'Create foodsaver passports for given users in region')]
    #[Route('/regions/{regionId}/passports', methods: ['POST'])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success', content: new OA\MediaType(mediaType: 'application/pdf',
        schema: new OA\Schema(description: 'Passport as PDF-File', type: 'string', format: 'binary')
    ))]
    #[OA\Response(response: Response::HTTP_NO_CONTENT, description: 'Success, no pdf generated')]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Insufficient permissions to create passport as ambassador in region.')]
    #[OA\Response(response: Response::HTTP_NOT_FOUND, description: 'User not found.')]
    public function createAsAmbassador(
        int $regionId,
        #[MapRequestPayload] CreateRegionPassportModel $regionPassportModel
    ): Response {
        $this->assertLoggedIn();

        if (!$this->passportPermissions->mayCreatePassportAsAmbassador($this->session->id(), $regionId)) {
            throw new AccessDeniedHttpException('Not permitted');
        }

        $areUsersInRegion = $this->passportGeneratorTransaction->areUsersInRegion($regionPassportModel->userIds, $regionId);
        if (!$areUsersInRegion->result) {
            throw new NotFoundHttpException($areUsersInRegion->message);
        }

        try {
            $pdf = $this->passportGeneratorTransaction->generatePassportAsAmbassador($regionPassportModel);
        } catch (\Exception $ex) {
            throw new BadRequestException($ex->getMessage());
        }
        if ($regionPassportModel->createPdf) {
            $pdfResponse = new Response($pdf);
            $pdfResponse->headers->set('Content-Type', 'application/pdf');

            return $pdfResponse;
        }

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    #[OA\Get(summary: 'Returns the wallet URL for the current user')]
    #[Route('/users/current/wallets/{walletType}', methods: ['GET'], requirements: ['walletType' => 'google|apple'])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success', content: new OA\JsonContent(type: 'object', properties: [
        new OA\Property(property: 'url', type: 'string', example: 'https://pay.google.com/gp/v/save/eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ...')
    ]))]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Insufficient permissions to create own passport.')]
    public function getWallet(string $walletType): Response
    {
        $this->assertLoggedIn();
        $userId = $this->session->id();
        if (!$this->session->user('role')) {
            throw new UnauthorizedHttpException('Invalid Session, please login again');
        }
        if (!$this->passportPermissions->mayCreatePassportAsUser($userId)) {
            throw new AccessDeniedHttpException('Not permitted');
        }

        try {
            $url = $this->passportGeneratorTransaction->createWallet($userId, $walletType);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
        if (empty($url)) {
            return $this->json(['error' => 'Could not generate wallet URL'], Response::HTTP_BAD_REQUEST);
        }

        return $this->respondOK(['url' => $url]);
    }
}
