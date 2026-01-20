<?php

namespace Foodsharing\RestApi;

use Foodsharing\Lib\Session;
use Foodsharing\Modules\Core\DBConstants\Unit\UnitType;
use Foodsharing\Modules\Foodsaver\FoodsaverGateway;
use Foodsharing\Modules\Region\RegionGateway;
use Foodsharing\Modules\WorkGroup\WorkGroupGateway;
use Foodsharing\Modules\WorkGroup\WorkGroupTransactions;
use Foodsharing\Permissions\WorkGroupPermissions;
use Foodsharing\RestApi\DTO\SendGroupRequestData;
use Foodsharing\RestApi\DTO\SendMailData;
use Foodsharing\RestApi\Models\Group\EditWorkGroupData;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

#[OA\Tag(name: 'groups')]
class WorkingGroupRestController extends AbstractFoodsharingRestController
{
    public function __construct(
        private readonly WorkGroupGateway $workGroupGateway,
        private readonly RegionGateway $regionGateway,
        private readonly FoodsaverGateway $foodsaverGateway,
        private readonly WorkGroupPermissions $workGroupPermissions,
        private readonly WorkGroupTransactions $groupTransactions,
        protected Session $session
    ) {
        parent::__construct($session);
    }

    #[OA\Post(
        description: 'If the user is already a member of the group, nothing happens.
        This endpoint can be used for adding someone else to a working group if you
        are allowed to edit that group or for joining a group if you are allowed to do so.',
        summary: 'Adds a member to a working group.')]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success')]
    #[OA\Response(response: Response::HTTP_UNAUTHORIZED, description: 'Not logged in')]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Insufficient permissions')]
    #[OA\Response(response: Response::HTTP_NOT_FOUND, description: 'Group not found')]
    #[Route('/groups/{groupId}/members/{memberId}', requirements: ['groupId' => Requirement::POSITIVE_INT, 'memberId' => Requirement::POSITIVE_INT], methods: ['POST'])]
    public function addMember(int $groupId, int $memberId): Response
    {
        $this->assertLoggedIn();

        $group = $this->workGroupGateway->getGroup($groupId);
        if (empty($group) || !UnitType::isGroup($group['type'])) {
            throw new NotFoundHttpException('Group does not exist');
        }

        if (!$this->workGroupPermissions->mayEdit($group)
            && !($memberId == $this->session->id() && $this->workGroupPermissions->mayJoin($group))) {
            throw new AccessDeniedHttpException('Not permitted');
        }

        $this->workGroupGateway->addToGroup($groupId, $memberId);

        // Add user to parent region if not already a member and if parent
        // region is not a working group itself (this is not wanted)
        $parentId = $group['parent_id'];
        $parentIsWorkingGroup = $this->workGroupGateway->regionIsWorkingGroup($parentId);
        if (!$parentIsWorkingGroup && !$this->regionGateway->hasMember($memberId, $parentId)) {
            // the parent region is a real region (not a working group) -> make
            // user a member of the parent region
            $this->regionGateway->addMember($memberId, $parentId);
        }

        return $this->respondOK();
    }

    #[OA\Post(summary: 'Updates the properties of a group.')]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success')]
    #[OA\Response(response: Response::HTTP_UNAUTHORIZED, description: 'Not logged in')]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Insufficient permissions')]
    #[OA\Response(response: Response::HTTP_NOT_FOUND, description: 'Group not found')]
    #[Route('/groups/{groupId}', requirements: ['groupId' => Requirement::POSITIVE_INT], methods: ['PATCH'])]
    public function editWorkingGroup(int $groupId, #[MapRequestPayload] EditWorkGroupData $groupData): Response
    {
        $this->assertLoggedIn();

        $group = $this->workGroupGateway->getGroup($groupId);
        if (empty($group)) {
            throw new NotFoundHttpException('Group does not exist');
        }
        if (!$this->workGroupPermissions->mayEdit($group)) {
            throw new AccessDeniedHttpException('Not permitted');
        }

        $this->groupTransactions->updateGroup($groupId, $groupData);

        return $this->respondOK();
    }

    #[OA\Post(summary: 'Sends a message to a group via email, including a custom message from the contact form.')]
    #[Route('/groups/{groupId}/mail', methods: ['POST'])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success, send will happen asynchroneously')]
    #[OA\Response(response: Response::HTTP_NOT_FOUND, description: 'Group not found or group has no email address')]
    #[OA\Response(response: Response::HTTP_BAD_REQUEST, description: 'Malformed data')]
    public function sendMailFromContactForm(int $groupId, #[MapRequestPayload] SendMailData $sendMailData): Response
    {
        $this->assertLoggedIn();

        $group = $this->workGroupGateway->getGroup($groupId);
        if (!$group || empty($group['email'])) {
            throw new NotFoundHttpException('Group does not exist or it does not have an e-mail address');
        }

        $userMail = $this->foodsaverGateway->getEmailAddress($this->session->id());
        $userName = $this->session->user('name');
        $recipients = [$group['email'], $userMail];

        $this->groupTransactions->sendMailToGroup($group['name'], $sendMailData, $userName, $this->session->id(), $recipients, $userMail);

        return $this->respondOK();
    }

    #[OA\Post(summary: 'Requests to join a group and provides motivation, ability, experience, and selected time message for mail to group.')]
    #[Route('/groups/{groupId}/applications', methods: ['POST'])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success')]
    #[OA\Response(response: Response::HTTP_UNAUTHORIZED, description: 'Not permitted to access these achievements')]
    #[OA\Response(response: Response::HTTP_NOT_FOUND, description: 'Not permitted to access these achievements')]
    #[OA\Response(response: Response::HTTP_BAD_REQUEST, description: 'Malformed data')]
    public function sendGroupRequest(int $groupId, #[MapRequestPayload] SendGroupRequestData $sendGroupRequestData): Response
    {
        $this->assertLoggedIn();

        $group = $this->workGroupGateway->getGroup($groupId);
        if (!$group) {
            throw new NotFoundHttpException('Group does not exist');
        }

        $this->groupTransactions->requestToGroup(
            $groupId,
            $this->session->id(),
            $sendGroupRequestData
        );

        return $this->respondOK();
    }
}
