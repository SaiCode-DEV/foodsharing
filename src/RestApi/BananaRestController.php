<?php

declare(strict_types=1);

namespace Foodsharing\RestApi;

use Foodsharing\Lib\Session;
use Foodsharing\Modules\Banana\BananaGateway as BananaBananaGateway;
use Foodsharing\Modules\Banana\BananaTransactions;
use Foodsharing\Modules\Banana\DTO\Banana;
use Foodsharing\Modules\Banana\DTO\BananaMetadata;
use Foodsharing\Modules\Foodsaver\FoodsaverGateway;
use Foodsharing\Permissions\BananaPermissions;
use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\Request\ParamFetcher;
use Nelmio\ApiDocBundle\Annotation\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Requirement\Requirement;

#[OA\Tag(name: 'banana')]
class BananaRestController extends AbstractFoodsharingRestController
{
    private const MIN_RATING_MESSAGE_LENGTH = 100;

    public function __construct(
        protected Session $session,
        private readonly FoodsaverGateway $foodsaverGateway,
        private readonly BananaBananaGateway $bananaGateway,
        private readonly BananaTransactions $bananaTransactions,
        private readonly BananaPermissions $bananaPermissions,
    ) {
        parent::__construct($this->session);
    }

    #[OA\Put(summary: 'Gives a banana to a user')]
    #[Rest\Put(path: 'user/{recipientId}/banana', requirements: ['recipientId' => Requirement::POSITIVE_INT])]
    #[Rest\RequestParam(name: 'message', nullable: false)]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success.', content: new Model(type: Banana::class))]
    #[OA\Response(response: Response::HTTP_UNAUTHORIZED, description: 'Not logged in.')]
    #[OA\Response(response: Response::HTTP_NOT_FOUND, description: 'User to rate does not exist.')]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Not allowed to give a banana to this user.')]
    #[OA\Response(response: Response::HTTP_BAD_REQUEST, description: 'Invalid parameters.')]
    public function addBanana(int $recipientId, ParamFetcher $paramFetcher): Response
    {
        $this->assertLoggedIn();
        $this->assertUserExists($recipientId);

        if (!$this->bananaPermissions->mayGiveBanana($recipientId)) {
            throw new AccessDeniedHttpException();
        }

        // check length of message
        $message = trim((string)$paramFetcher->get('message'));
        if (strlen($message) < self::MIN_RATING_MESSAGE_LENGTH) {
            throw new BadRequestHttpException('text too short: ' . strlen($message) . ' < ' . self::MIN_RATING_MESSAGE_LENGTH);
        }

        $banana = $this->bananaTransactions->addBanana($recipientId, $this->session->id(), $message);

        return $this->respondOK($banana);
    }

    #[OA\Put(summary: 'Deletes a banana')]
    #[Rest\Delete('user/{recipientId}/banana/{senderId}', requirements: ['recipientId' => Requirement::POSITIVE_INT, 'senderId' => Requirement::POSITIVE_INT])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success.')]
    #[OA\Response(response: Response::HTTP_UNAUTHORIZED, description: 'Not logged in.')]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Insufficient permissions to delete that banana.')]
    #[OA\Response(response: Response::HTTP_NOT_FOUND, description: 'Banana does not exist.')]
    public function deleteBanana(int $recipientId, int $senderId): Response
    {
        $this->assertLoggedIn();

        if (!$this->bananaPermissions->mayDeleteBanana($recipientId)) {
            throw new AccessDeniedHttpException();
        }

        if (!$this->bananaGateway->deleteBanana($recipientId, $senderId)) {
            throw new NotFoundHttpException();
        }

        return $this->respondOK();
    }

    #[OA\Get(summary: 'Returns basic metadata about the bananas of a user')]
    #[Rest\Get('user/{userId}/banana/meta', requirements: ['userId' => Requirement::POSITIVE_INT])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success.', content: new Model(type: BananaMetadata::class))]
    #[OA\Response(response: Response::HTTP_UNAUTHORIZED, description: 'Not logged in.')]
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
    #[Rest\Get('user/{recipientId}/banana/received', requirements: ['recipientId' => Requirement::POSITIVE_INT])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success.', content: new OA\JsonContent(
        type: 'array', items: new OA\Items(ref: new Model(type: Banana::class))
    ))]
    #[OA\Response(response: Response::HTTP_UNAUTHORIZED, description: 'Not logged in.')]
    #[OA\Response(response: Response::HTTP_NOT_FOUND, description: 'User does not exist.')]
    public function getReceivedBananas(int $recipientId): Response
    {
        $this->assertLoggedIn();
        $this->assertUserExists($recipientId);

        $bananas = $this->bananaGateway->getReceivedBananas($recipientId);

        return $this->respondOK($bananas);
    }

    #[OA\Get(summary: 'Returns the bananas given by a user')]
    #[Rest\Get('user/{senderId}/banana/sent', requirements: ['senderId' => Requirement::POSITIVE_INT])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success.', content: new OA\JsonContent(
        type: 'array', items: new OA\Items(ref: new Model(type: Banana::class))
    ))]
    #[OA\Response(response: Response::HTTP_UNAUTHORIZED, description: 'Not logged in.')]
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
            throw new NotFoundHttpException();
        }
    }
}
