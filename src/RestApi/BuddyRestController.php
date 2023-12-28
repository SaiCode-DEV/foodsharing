<?php

namespace Foodsharing\RestApi;

use Foodsharing\Lib\Session;
use Foodsharing\Modules\Buddy\BuddyGateway;
use Foodsharing\Modules\Buddy\BuddyTransactions;
use FOS\RestBundle\Controller\AbstractFOSRestController;
use FOS\RestBundle\Controller\Annotations as Rest;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

class BuddyRestController extends AbstractFOSRestController
{
    private BuddyTransactions $buddyTransactions;
    private BuddyGateway $buddyGateway;
    private Session $session;

    public function __construct(
        BuddyTransactions $buddyTransactions,
        BuddyGateway $buddyGateway,
        Session $session
    ) {
        $this->buddyTransactions = $buddyTransactions;
        $this->buddyGateway = $buddyGateway;
        $this->session = $session;
    }

    /**
     * Sends a buddy request to a user.
     */
    #[OA\Tag(name: 'buddy')]
    #[OA\Parameter(name: 'userId', in: 'path', schema: new OA\Schema(type: 'integer'), description: 'which user to send the request to')]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success.', content: new OA\JsonContent(type: 'object', properties: [new OA\Property(property: 'isBuddy', type: 'boolean', description: "whether the other user is now this user's buddy")]))]
    #[OA\Response(response: Response::HTTP_BAD_REQUEST, description: 'Already send a request to that user.')]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Insufficient permissions to send the request.')]
    #[Rest\Put('buddy/{userId}', requirements: ['userId' => "\d+"])]
    public function sendRequestAction(int $userId): Response
    {
        if (!$this->session->id()) {
            throw new UnauthorizedHttpException('');
        }

        if ($this->buddyGateway->hasSentBuddyRequest($this->session->id(), $userId)) {
            throw new BadRequestHttpException('You cannot send mutliple requests');
        }

        $accepting = $this->buddyGateway->hasSentBuddyRequest($userId, $this->session->id());
        if ($accepting) {
            $this->buddyTransactions->acceptBuddyRequest($userId);
        } else {
            $this->buddyTransactions->sendBuddyRequest($userId);
        }

        return $this->handleView($this->view(['isBuddy' => $accepting], Response::HTTP_OK));
    }

    /**
     * Removes a buddy request to a user.
     */
    #[OA\Parameter(name: 'userId', in: 'path', schema: new OA\Schema(type: 'integer'), description: 'which user to remove the request to')]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success.', content: new OA\JsonContent(type: 'boolean'))]
    #[OA\Response(response: Response::HTTP_BAD_REQUEST, description: 'Not currently requested to be a buddy of that user.')]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Insufficient permissions to send the request.')]
    #[OA\Tag(name: 'buddy')]
    #[Rest\Delete('buddy/{userId}', requirements: ['userId' => "\d+"])]
    public function removeRequestAction(int $userId): Response
    {
        if (!$this->session->id()) {
            throw new UnauthorizedHttpException('');
        }

        if (!$this->buddyGateway->hasSentBuddyRequest($this->session->id(), $userId)) {
            throw new NotFoundHttpException('You cannot delete a request you did not send');
        }

        $this->buddyTransactions->removeBuddyRequest($userId);

        return $this->handleView($this->view(true, Response::HTTP_OK));
    }
}
