<?php

declare(strict_types=1);

namespace Foodsharing\RestApi;

use Foodsharing\Lib\Session;
use Foodsharing\Modules\Banana\BananaGateway;
use Foodsharing\Modules\Banana\BananaTransactions;
use Foodsharing\Modules\Banana\DTO\Banana;
use Foodsharing\Modules\Banana\DTO\BananaMessage;
use Foodsharing\Modules\Banana\DTO\BananaMetadata;
use Foodsharing\Modules\Foodsaver\FoodsaverGateway;
use Foodsharing\Permissions\BananaPermissions;
use Nelmio\ApiDocBundle\Annotation\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

#[OA\Tag(name: 'banana')]
#[OA\Response(response: Response::HTTP_UNAUTHORIZED, description: 'Not logged in.')]
class BananaRestController extends AbstractFoodsharingRestController
{
    public const int MIN_RATING_MESSAGE_LENGTH = 100;

    public function __construct(
        protected Session $session,
        private readonly FoodsaverGateway $foodsaverGateway,
        private readonly BananaGateway $bananaGateway,
        private readonly BananaTransactions $bananaTransactions,
        private readonly BananaPermissions $bananaPermissions,
    ) {
        parent::__construct($this->session);
    }

    #[OA\Post(summary: 'Gives a banana to a user')]
    #[Route('users/{recipientId}/bananas', methods: ['POST'], requirements: ['recipientId' => Requirement::POSITIVE_INT])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success.', content: new Model(type: Banana::class))]
    #[OA\Response(response: Response::HTTP_NOT_FOUND, description: 'User to rate does not exist.')]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Not allowed to give a banana to this user.')]
    #[OA\Response(response: Response::HTTP_BAD_REQUEST, description: 'Invalid parameters.')]
    public function addBanana(int $recipientId, #[MapRequestPayload] BananaMessage $bananaMessage): Response
    {
        $this->assertLoggedIn();
        $this->assertUserExists($recipientId);

        if (!$this->bananaPermissions->mayGiveBanana($recipientId)) {
            throw new AccessDeniedHttpException('Not permitted');
        }

        $banana = $this->bananaTransactions->addBanana($recipientId, $this->session->id(), $bananaMessage->message);

        return $this->respondOK($banana);
    }

    #[OA\Put(summary: 'Deletes a banana')]
    #[Route('users/{recipientId}/bananas/{senderId}', methods: ['DELETE'], requirements: ['recipientId' => Requirement::POSITIVE_INT, 'senderId' => Requirement::POSITIVE_INT])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success.')]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Insufficient permissions to delete that banana.')]
    #[OA\Response(response: Response::HTTP_NOT_FOUND, description: 'Banana does not exist.')]
    public function deleteBanana(int $recipientId, int $senderId): Response
    {
        $this->assertLoggedIn();

        if (!$this->bananaPermissions->mayDeleteBanana($recipientId, $senderId)) {
            throw new AccessDeniedHttpException('Not permitted');
        }

        if (!$this->bananaGateway->deleteBanana($recipientId, $senderId)) {
            throw new NotFoundHttpException('Banana does not exist');
        }

        return $this->respondOK();
    }

    #[OA\Get(summary: 'Returns basic metadata about the bananas of a user')]
    #[Route('users/{userId}/bananas/meta', methods: ['GET'], requirements: ['userId' => Requirement::POSITIVE_INT])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success.', content: new Model(type: BananaMetadata::class))]
    #[OA\Response(response: Response::HTTP_NOT_FOUND, description: 'User does not exist.')]
    public function getBananaMetadata(int $userId): Response
    {
        $this->assertLoggedIn();
        $this->assertUserExists($userId);

        $metadata = new BananaMetadata(
            receivedCount: $this->bananaGateway->getReceivedBananasCount($userId),
            mayGiveBanana: $this->bananaPermissions->mayGiveBanana($userId),
            mayDeleteBananas: $this->bananaPermissions->mayDeleteBananas(),
        );

        return $this->respondOK($metadata);
    }

    #[OA\Get(summary: 'Returns the bananas given to a user')]
    #[Route('users/{recipientId}/bananas/received', methods: ['GET'], requirements: ['recipientId' => Requirement::POSITIVE_INT])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success.', content: new OA\JsonContent(
        type: 'array', items: new OA\Items(ref: new Model(type: Banana::class))
    ))]
    #[OA\Response(response: Response::HTTP_NOT_FOUND, description: 'User does not exist.')]
    public function getReceivedBananas(int $recipientId): Response
    {
        $this->assertLoggedIn();
        $this->assertUserExists($recipientId);

        $bananas = $this->bananaGateway->getReceivedBananas($recipientId);

        return $this->respondOK($bananas);
    }

    #[OA\Get(summary: 'Returns the bananas given by a user')]
    #[Route('users/{senderId}/bananas/sent', methods: ['GET'], requirements: ['senderId' => Requirement::POSITIVE_INT])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success.', content: new OA\JsonContent(
        type: 'array', items: new OA\Items(ref: new Model(type: Banana::class))
    ))]
    #[OA\Response(response: Response::HTTP_NOT_FOUND, description: 'User does not exist.')]
    public function getSentBananas(int $senderId): Response
    {
        $this->assertLoggedIn();
        $this->assertUserExists($senderId);

        $bananas = $this->bananaGateway->getSentBananas($senderId);

        return $this->respondOK($bananas);
    }

    private function assertUserExists(int $userId): void
    {
        if (!$this->foodsaverGateway->foodsaverExists($userId)) {
            throw new NotFoundHttpException('User does not exist: ' . $userId);
        }
    }
}
