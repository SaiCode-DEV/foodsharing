<?php

namespace Foodsharing\RestApi;

use Foodsharing\Lib\BigBlueButton;
use Foodsharing\Lib\DTO\ConferenceRoom;
use Foodsharing\Lib\Session;
use Foodsharing\Modules\Group\GroupGateway;
use Foodsharing\Modules\Group\GroupTransactions;
use Foodsharing\Modules\Region\RegionGateway;
use Foodsharing\Modules\Unit\CurrentUserUnitsInterface;
use Foodsharing\Permissions\RegionPermissions;
use Foodsharing\Utility\ImageHelper;
use League\Container\Exception\NotFoundException;
use Nelmio\ApiDocBundle\Annotation\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

#[OA\Tag(name: 'region')]
#[OA\Response(response: Response::HTTP_UNAUTHORIZED, description: 'Not logged in')]
class GroupRestController extends AbstractFoodsharingRestController
{
    public function __construct(
        protected Session $session,
        private readonly GroupGateway $groupGateway,
        private readonly ImageHelper $imageService,
        private readonly RegionPermissions $regionPermissions,
        private readonly GroupTransactions $groupTransactions,
        private readonly CurrentUserUnitsInterface $currentUserUnits,
        private readonly RegionGateway $regionGateway,
        private readonly BigBlueButton $bbb,
    ) {
        parent::__construct($session);
    }

    #[OA\Delete(summary: 'Delete a region or a working group.')]
    #[Route('regions/{regionId}', methods: ['DELETE'], requirements: ['regionId' => Requirement::POSITIVE_INT])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success')]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Not permitted')]
    #[OA\Response(response: Response::HTTP_CONFLICT, description: 'Unit contains subelements preventing the deletion')]
    public function deleteGroup(int $regionId): Response
    {
        $this->assertLoggedIn();
        if (!$this->regionPermissions->mayAdministrateRegions()) {
            throw new AccessDeniedHttpException('Not permitted');
        }

        if ($this->groupTransactions->hasSubElements($regionId)) {
            throw new ConflictHttpException('Unit contains subelements preventing the deletion');
        }

        // own message so the admin tool can show the actual reason
        if ($this->groupGateway->hasMailboxEmails($regionId)) {
            throw new ConflictHttpException('The region\'s mailbox still contains emails preventing the deletion');
        }

        $this->groupGateway->deleteGroup($regionId);

        return $this->respondOK();
    }

    #[OA\Get(summary: 'Returns the join URL of a given groups conference.')]
    #[Route('regions/{regionId}/conference', methods: ['GET'], requirements: ['regionId' => Requirement::POSITIVE_INT])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success', content: new Model(type: ConferenceRoom::class))]
    #[OA\Response(response: Response::HTTP_FOUND, description: 'Redirect to personalized conference URL')]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Not permitted')]
    #[OA\Response(response: Response::HTTP_NOT_FOUND, description: 'Region doesn\'t have a confernece')]
    #[OA\Response(response: Response::HTTP_SERVICE_UNAVAILABLE, description: 'Conferences currently not available')]
    public function joinConference(Request $request, int $regionId, #[MapQueryParameter] ?bool $redirect): Response
    {
        $this->assertLoggedIn();
        if (!$this->currentUserUnits->mayBezirk($regionId)) {
            throw new AccessDeniedHttpException('Not permitted');
        }
        $group = $this->regionGateway->getRegion($regionId);
        if (!$this->regionPermissions->hasConference($group['type'])) {
            throw new NotFoundException('This region does not support conferences');
        }

        $httpHost = $request->server->get('HTTP_HOST', BASE_URL);
        $host = str_replace('beta.', '', $httpHost);
        $key = 'region-' . $regionId;
        $conference = $this->bbb->createRoom($group['name'], $key, $host);
        if (!$conference) {
            throw new ServiceUnavailableHttpException(null, 'Conferences currently not available');
        }

        $redirect ??= true;
        if ($redirect) {
            // We only return the personalized URL when redirecting to make it unlikely that the user forwards the personalized BBB join URL

            $name = $this->session->user('name') . ' (' . $this->session->id() . ')';
            $avatar = 'https://' . $host . $this->imageService->img($this->session->user('photo'));

            return $this->redirect($this->bbb->joinURL($key, $name, $avatar, true));
        }

        // Without the redirect, we just return information about the conference
        return $this->respondOK($conference);
    }
}
