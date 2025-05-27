<?php

namespace Foodsharing\RestApi;

use Foodsharing\Lib\BigBlueButton;
use Foodsharing\Lib\Session;
use Foodsharing\Modules\Group\GroupGateway;
use Foodsharing\Modules\Group\GroupTransactions;
use Foodsharing\Modules\Region\RegionGateway;
use Foodsharing\Modules\Unit\CurrentUserUnitsInterface;
use Foodsharing\Modules\Unit\DTO\UserUnit;
use Foodsharing\Permissions\RegionPermissions;
use Foodsharing\RestApi\Models\Group\UserGroupModel;
use Foodsharing\Utility\ImageHelper;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Requirement\Requirement;

#[OA\Tag(name: 'groups', description: 'Endpoints for groups including regions and working groups')]
class GroupRestController extends AbstractFoodsharingRestController
{
    public function __construct(
        private readonly GroupGateway $groupGateway,
        private readonly ImageHelper $imageService,
        private readonly RegionPermissions $regionPermissions,
        private readonly GroupTransactions $groupTransactions,
        private readonly CurrentUserUnitsInterface $currentUserUnits,
        private readonly RegionGateway $regionGateway,
        protected Session $session,
    ) {
        parent::__construct($this->session);
    }

    #[OA\Delete(summary: 'Deletes a region or a working group.')]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success')]
    #[OA\Response(response: Response::HTTP_UNAUTHORIZED, description: 'Not logged in')]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Insufficient permissions')]
    #[OA\Response(response: Response::HTTP_CONFLICT, description: 'Group still contains elements')]
    #[OA\PathParameter(name: 'groupId', description: 'Id of the group to delete', schema: new OA\Schema(type: 'integer'))]
    #[Route('/groups/{groupId}', requirements: ['groupId' => Requirement::POSITIVE_INT], methods: ['DELETE'])]
    public function deleteGroup(int $groupId): Response
    {
        $this->assertLoggedIn();
        if (!$this->regionPermissions->mayAdministrateRegions()) {
            throw new AccessDeniedHttpException();
        }

        // check if the group still contains elements
        if ($this->groupTransactions->hasSubElements($groupId)) {
            throw new ConflictHttpException('This region contains subelements preventing the deletion.');
        }

        $this->groupGateway->deleteGroup($groupId);

        return $this->respondOK();
    }

    #[Route('/groups/{groupId}/conference', requirements: ['groupId' => Requirement::POSITIVE_INT], methods: ['GET'])]
    #[OA\Get(summary: 'Returns the join URL of a given groups conference.')]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success without a redirect')]
    #[OA\Response(response: Response::HTTP_FOUND, description: 'Success with redirect to the conference URL')]
    #[OA\Response(response: Response::HTTP_UNAUTHORIZED, description: 'Not logged in')]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'The region does not have a conference or insufficient permissions to join it')]
    #[OA\QueryParameter(
        name: 'redirect',
        description: 'Should the response perform a 301 redirect to the actual conference?',
        in: 'query',
        schema: new OA\Schema(type: 'boolean', default: false)
    )]
    public function joinConference(Request $request, int $groupId, BigBlueButton $bbb, #[MapQueryParameter] bool $redirect = false): Response
    {
        $this->assertLoggedIn();
        if (!$this->currentUserUnits->mayBezirk($groupId)) {
            throw new AccessDeniedHttpException();
        }
        $group = $this->regionGateway->getRegion($groupId);
        if (!$this->regionPermissions->hasConference($group['type'])) {
            throw new AccessDeniedHttpException('This region does not support conferences');
        }

        $httpHost = $request->server->get('HTTP_HOST', BASE_URL);
        $host = str_replace('beta.', '', $httpHost);
        $key = 'region-' . $groupId;
        $conference = $bbb->createRoom($group['name'], $key, $host);
        if (!$conference) {
            throw new HttpException(500, 'Conferences currently not available');
        }
        $data = [
            'dialin' => $conference['dialin'],
            'id' => $conference['id'],
        ];

        $name = $this->session->user('name') . ' (' . $this->session->id() . ')';
        $avatar = 'https://' . $host . $this->imageService->img($this->session->user('photo'));

        /* We do a 302 redirect directly to have less likeliness that the user forwards the BBB join URL as this is already personalized */
        if ($redirect) {
            return $this->redirect($bbb->joinURL($key, $name, $avatar, true));
        }

        /* Without the redirect, we return information about the conference */
        return $this->respondOK($data);
    }

    #[OA\Get(summary: 'Returns a list of all groups of the user')]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success', content: new OA\JsonContent(
        description: 'The groups of the user',
        type: 'array',
        items: new OA\Items(ref: UserGroupModel::class, type: 'object')
    ))]
    #[OA\Response(response: Response::HTTP_UNAUTHORIZED, description: 'Not logged in')]
    #[Route('/user/current/groups', methods: ['GET'])]
    public function listMyWorkingGroups(): Response
    {
        $this->assertLoggedIn();

        $groups = $this->groupTransactions->getUserGroups($this->session->id());

        $rspGroups = array_map(fn (UserUnit $group): UserGroupModel => UserGroupModel::createFrom($group), $groups);

        return $this->respondOK($rspGroups);
    }
}
