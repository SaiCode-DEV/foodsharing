<?php

namespace Foodsharing\RestApi;

use Foodsharing\Lib\Session;
use Foodsharing\Modules\Core\DBConstants\Unit\UnitType;
use Foodsharing\Modules\Foodsaver\FoodsaverGateway;
use Foodsharing\Modules\Foodsaver\Profile;
use Foodsharing\Modules\WorkGroup\WorkGroupGateway;
use Foodsharing\Modules\WorkGroup\WorkGroupTransactions;
use Foodsharing\Permissions\WorkGroupPermissions;
use Foodsharing\RestApi\DTO\SendGroupRequestData;
use Foodsharing\RestApi\DTO\SendMailData;
use Foodsharing\RestApi\Models\Group\EditWorkGroupData;
use FOS\RestBundle\Controller\Annotations as Rest;
use Nelmio\ApiDocBundle\Annotation\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

#[OA\Tag(name: 'groups')]
class WorkingGroupRestController extends AbstractFoodsharingRestController
{
    public function __construct(
        private readonly WorkGroupGateway $workGroupGateway,
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
    #[OA\Response(
        response: Response::HTTP_OK,
        description: 'Success',
        content: new Model(type: Profile::class)
    )]
    #[OA\Response(response: Response::HTTP_UNAUTHORIZED, description: 'Not logged in')]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Insufficient permissions')]
    #[OA\Response(response: Response::HTTP_NOT_FOUND, description: 'Group not found')]
    #[Rest\Post('groups/{groupId}/members/{memberId}', requirements: ['groupId' => '\d+', 'memberId' => '\d+'])]
    public function addMember(int $groupId, int $memberId): Response
    {
        $this->assertLoggedIn();

        $group = $this->workGroupGateway->getGroup($groupId);
        if (empty($group) || !UnitType::isGroup($group['type'])) {
            throw new NotFoundHttpException();
        }

        if (!$this->workGroupPermissions->mayEdit($group)
            && !($memberId == $this->session->id() && $this->workGroupPermissions->mayJoin($group))) {
            throw new AccessDeniedHttpException();
        }

        $this->workGroupGateway->addToGroup($groupId, $memberId);
        $user = $this->foodsaverGateway->getProfile($memberId);

        return $this->respondOK($user);
    }

    #[OA\Post(summary: 'Updates the properties of a group.')]
    #[OA\Response(response: Response::HTTP_NO_CONTENT, description: 'Success')]
    #[OA\Response(response: Response::HTTP_UNAUTHORIZED, description: 'Not logged in')]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Insufficient permissions')]
    #[OA\Response(response: Response::HTTP_NOT_FOUND, description: 'Group not found')]
    #[Rest\Patch('groups/{groupId}', requirements: ['groupId' => '\d+'])]
    public function editWorkingGroup(int $groupId, #[MapRequestPayload] EditWorkGroupData $groupData): Response
    {
        $this->assertLoggedIn();

        $group = $this->workGroupGateway->getGroup($groupId);
        if (empty($group)) {
            throw new NotFoundHttpException();
        }
        if (!$this->workGroupPermissions->mayEdit($group)) {
            throw new AccessDeniedHttpException();
        }

        $this->groupTransactions->updateGroup($groupId, $groupData);

        return $this->respondOK($groupData);
    }

    #[OA\Post(summary: 'Sends a message to a group via email, including a custom message from the contact form.')]
    #[Rest\Post('groups/{groupId}/mail')]
    #[OA\Response(response: Response::HTTP_ACCEPTED, description: 'Success, send will happen asynchron')]
    #[OA\Response(response: Response::HTTP_UNAUTHORIZED, description: 'Not permitted to access these achievements')]
    #[OA\Response(response: Response::HTTP_NOT_FOUND, description: 'Not permitted to access these achievements')]
    #[OA\Response(response: Response::HTTP_UNPROCESSABLE_ENTITY, description: 'Validation errors')]
    #[OA\Response(response: Response::HTTP_BAD_REQUEST, description: 'Malformed data')]
    #[OA\Response(response: Response::HTTP_UNSUPPORTED_MEDIA_TYPE, description: 'Unsupported deserialization formats')]
    public function sendMailFromContactForm(int $groupId, #[MapRequestPayload] SendMailData $sendMailData): Response
    {
        $this->assertLoggedIn();

        $group = $this->workGroupGateway->getGroup($groupId);
        if (!$group || empty($group['email'])) {
            throw new NotFoundHttpException();
        }

        $userMail = $this->foodsaverGateway->getEmailAddress($this->session->id());
        $userName = $this->session->user('name');
        $recipients = [$group['email'], $userMail];

        $this->groupTransactions->sendMailToGroup($group['name'], $sendMailData, $userName, $this->session->id(), $recipients, $userMail);

        return $this->handleView($this->view([], Response::HTTP_ACCEPTED));
    }

    #[OA\Post(summary: 'Requests to join a group and provides motivation, ability, experience, and selected time message for mail to group.')]
    #[Rest\Post('groups/{groupId}/request')]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success')]
    #[OA\Response(response: Response::HTTP_UNAUTHORIZED, description: 'Not permitted to access these achievements')]
    #[OA\Response(response: Response::HTTP_NOT_FOUND, description: 'Not permitted to access these achievements')]
    #[OA\Response(response: Response::HTTP_UNPROCESSABLE_ENTITY, description: 'Validation errors')]
    #[OA\Response(response: Response::HTTP_BAD_REQUEST, description: 'Malformed data')]
    #[OA\Response(response: Response::HTTP_UNSUPPORTED_MEDIA_TYPE, description: 'Unsupported deserialization formats')]
    public function sendGroupRequest(int $groupId, #[MapRequestPayload] SendGroupRequestData $sendGroupRequestData): Response
    {
        $this->assertLoggedIn();

        $group = $this->workGroupGateway->getGroup($groupId);
        if (!$group) {
            throw new NotFoundHttpException();
        }

        $this->groupTransactions->requestToGroup(
            $groupId,
            $this->session->id(),
            $sendGroupRequestData
        );

        return $this->respondOK();
    }
}
