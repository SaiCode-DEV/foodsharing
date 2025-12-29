<?php

namespace Foodsharing\RestApi;

use Foodsharing\Lib\Session;
use Foodsharing\Modules\Buddy\BuddyGateway;
use Foodsharing\Modules\Buddy\BuddyTransactions;
use Foodsharing\Modules\Buddy\DTO\BuddyList;
use Nelmio\ApiDocBundle\Annotation\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

#[OA\Tag(name: 'buddy')]
#[OA\Response(response: Response::HTTP_UNAUTHORIZED, description: 'Not logged in.')]
class BuddyRestController extends AbstractFoodsharingRestController
{
    public function __construct(
        private readonly BuddyTransactions $buddyTransactions,
        private readonly BuddyGateway $buddyGateway,
        protected Session $session
    ) {
        parent::__construct($this->session);
    }

    #[OA\Post(summary: 'Sends a buddy request to a user.')]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success.', content: new OA\JsonContent(type: 'object', properties: [
        new OA\Property(property: 'isBuddy', type: 'boolean', description: 'whether the other user is now this user\'s buddy')
    ]))]
    #[OA\Response(response: Response::HTTP_BAD_REQUEST, description: 'Already send a request to that user.')]
    #[Route('users/{userId}/buddies', methods: ['POST'], requirements: ['userId' => Requirement::POSITIVE_INT])]
    public function sendRequest(int $userId): Response
    {
        $this->assertLoggedIn();

        if ($this->buddyGateway->hasSentBuddyRequest($this->session->id(), $userId)) {
            throw new BadRequestHttpException('You cannot send mutliple requests');
        }

        $accepting = $this->buddyGateway->hasSentBuddyRequest($userId, $this->session->id());
        if ($accepting) {
            $this->buddyTransactions->acceptBuddyRequest($userId);
        } else {
            $this->buddyTransactions->sendBuddyRequest($userId);
        }

        return $this->respondOK(['isBuddy' => $accepting]);
    }

    #[OA\Delete(summary: 'Removes a buddy request to a user.')]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success')]
    #[OA\Response(response: Response::HTTP_NOT_FOUND, description: 'There was no request to be removed.')]
    #[Route('users/{userId}/buddies', methods: ['DELETE'], requirements: ['userId' => Requirement::POSITIVE_INT])]
    public function removeRequest(int $userId): Response
    {
        $this->assertLoggedIn();

        if (!$this->buddyGateway->hasSentBuddyRequest($this->session->id(), $userId)) {
            throw new NotFoundHttpException('You cannot delete a request you did not send');
        }

        $this->buddyTransactions->removeBuddyRequest($userId);

        return $this->respondOK();
    }

    #[OA\Get(summary: 'Returns a list of all buddies with id, name, and photo.')]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success.', content: new Model(type: BuddyList::class))]
    #[Route('users/current/buddies', methods: ['GET'])]
    public function listBuddies(): Response
    {
        $this->assertLoggedIn();
        $buddies = $this->buddyTransactions->listBuddies();

        return $this->respondOK($buddies);
    }
}
