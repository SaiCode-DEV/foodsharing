<?php

namespace Foodsharing\RestApi;

use Foodsharing\Lib\Session;
use Foodsharing\Modules\Event\EventGateway;
use Foodsharing\Modules\Event\InvitationStatus;
use Foodsharing\Permissions\EventPermissions;
use FOS\RestBundle\Controller\AbstractFOSRestController;
use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\Request\ParamFetcher;
use OpenApi\Annotations as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

class EventRestController extends AbstractFOSRestController
{
    public function __construct(
        private readonly EventGateway $eventGateway,
        private readonly EventPermissions $eventPermissions,
        private readonly Session $session
    ) {
    }

    /**
     * Updates the user's response to an invitation.
     *
     * @OA\Response(response="204", description="Success")
     * @OA\Response(response="400", description="Invalid status code")
     * @OA\Response(response="401", description="Not logged in")
     * @OA\Response(response="403", description="Insufficient permissions to join the event")
     * @OA\Tag(name="events")
     * @Rest\Patch("users/current/events/{eventId}/invitation", requirements={"eventId" = "\d+"})
     * @Rest\RequestParam(name="status", requirements="\d+", nullable=false)
     */
    public function setResponseAction(int $eventId, ParamFetcher $paramFetcher): Response
    {
        $fsId = $this->session->id();
        if (!$fsId) {
            throw new UnauthorizedHttpException('');
        }

        // check that the event exists
        $event = $this->eventGateway->getEvent($eventId);
        if (empty($event)) {
            throw new NotFoundHttpException();
        }
        if (!$this->eventPermissions->mayJoinEvent($event)) {
            throw new AccessDeniedHttpException();
        }
        // check that the status is valid
        $status = (int)$paramFetcher->get('status');
        if (!InvitationStatus::isValidStatus($status)) {
            throw new BadRequestHttpException();
        }

        $this->eventGateway->setInviteStatus($eventId, [$fsId], $status);

        return $this->handleView($this->view([], Response::HTTP_NO_CONTENT));
    }
}
