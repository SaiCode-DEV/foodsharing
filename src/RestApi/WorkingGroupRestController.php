<?php

namespace Foodsharing\RestApi;

use Foodsharing\Lib\Session;
use Foodsharing\Modules\Core\DBConstants\Unit\UnitType;
use Foodsharing\Modules\Foodsaver\FoodsaverGateway;
use Foodsharing\Modules\Foodsaver\Profile;
use Foodsharing\Modules\WorkGroup\WorkGroupGateway;
use Foodsharing\Modules\WorkGroup\WorkGroupTransactions;
use Foodsharing\Permissions\WorkGroupPermissions;
use Foodsharing\RestApi\Models\Group\EditWorkGroupData;
use FOS\RestBundle\Controller\AbstractFOSRestController;
use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\Request\ParamFetcher;
use Nelmio\ApiDocBundle\Annotation\Model;
use OpenApi\Annotations as OA;
use OpenApi\Attributes as OA2;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class WorkingGroupRestController extends AbstractFOSRestController
{
    private readonly WorkGroupGateway $workGroupGateway;
    private readonly FoodsaverGateway $foodsaverGateway;
    private readonly Session $session;
    private readonly WorkGroupPermissions $workGroupPermissions;
    private readonly WorkGroupTransactions $groupTransactions;

    public function __construct(
        WorkGroupGateway $workGroupGateway,
        FoodsaverGateway $foodsaverGateway,
        Session $session,
        WorkGroupPermissions $workGroupPermissions,
        WorkGroupTransactions $groupTransactions
    ) {
        $this->workGroupGateway = $workGroupGateway;
        $this->foodsaverGateway = $foodsaverGateway;
        $this->session = $session;
        $this->workGroupPermissions = $workGroupPermissions;
        $this->groupTransactions = $groupTransactions;
    }

    /**
     * Adds a member to a working group. If the user is already a member of the group, nothing happens. This
     * endpoint can be used for adding someone else to a working group if you are allowed to edit that group or
     * for joining a group if you allowed to do so.
     *
     * @OA\Response(
     * 		response="200",
     * 		description="Success",
     *      @Model(type=Profile::class)
     * )
     * @OA\Response(response="401", description="Not logged in")
     * @OA\Response(response="403", description="Insufficient permissions")
     * @OA\Response(response="404", description="Group not found")
     * @OA\Tag(name="groups")
     */
    #[Rest\Post('groups/{groupId}/members/{memberId}', requirements: ['groupId' => '\d+', 'memberId' => '\d+'])]
    public function addMember(int $groupId, int $memberId): Response
    {
        if (!$this->session->mayRole()) {
            throw new UnauthorizedHttpException('');
        }

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

        return $this->handleView($this->view($user, 200));
    }

    /**
     * Updates the properties of a group.
     *
     * @OA\Response(response="204", description="Success")
     * @OA\Response(response="401", description="Not logged in")
     * @OA\Response(response="403", description="Insufficient permissions")
     * @OA\Response(response="404", description="Group not found")
     * @OA\Tag(name="groups")
     * @OA\RequestBody(@Model(type=EditWorkGroupData::class))
     */
    #[Rest\Patch('groups/{groupId}', requirements: ['groupId' => '\d+'])]
    #[ParamConverter('groupData', class: EditWorkGroupData::class, converter: 'fos_rest.request_body')]
    public function editWorkingGroup(int $groupId, EditWorkGroupData $groupData, ValidatorInterface $validator): Response
    {
        // check permissions
        if (!$this->session->id()) {
            throw new UnauthorizedHttpException('');
        }
        $group = $this->workGroupGateway->getGroup($groupId);
        if (empty($group)) {
            throw new NotFoundHttpException();
        }
        if (!$this->workGroupPermissions->mayEdit($group)) {
            throw new AccessDeniedHttpException();
        }

        // validate the form data
        $errors = $validator->validate($groupData);
        if ($errors->count() > 0) {
            $firstError = $errors->get(0);
            throw new BadRequestHttpException(json_encode(['field' => $firstError->getPropertyPath(), 'message' => $firstError->getMessage()]));
        }

        $this->groupTransactions->updateGroup($groupId, $groupData);

        return $this->handleView($this->view($groupData, 200));
    }

    #[OA2\Post(summary: 'Sends a message to a group via email, including a custom message from the contact form.')]
    #[Rest\Post('groups/{groupId}/mail')]
    #[OA2\Tag(name: 'groups')]
    #[Rest\RequestParam(name: 'message', requirements: '.*', description: 'message for mail to group')]
    public function sendMailFromContactForm(int $groupId, ParamFetcher $paramFetcher): Response
    {
        $userId = $this->session->id();
        if (!$userId) {
            throw new UnauthorizedHttpException('');
        }

        $message = $paramFetcher->get('message');

        $group = $this->workGroupGateway->getGroup($groupId);
        if (!$group || empty($group['email'])) {
            throw new NotFoundHttpException();
        }

        $userMail = $this->session->user('email');
        $userName = $this->session->user('name');
        $recipients = [$group['email'], $userMail];

        $this->groupTransactions->sendMailToGroup($group['name'], $message, $userName, $userId, $recipients, $userMail);

        return $this->handleView($this->view([], 200));
    }

    #[OA2\Post(summary: 'Requests to join a group and provides motivation, ability, experience, and selected time message for mail to group.')]
    #[Rest\Post('groups/{groupId}/request')]
    #[OA2\Tag(name: 'groups')]
    #[Rest\RequestParam(name: 'motivation', requirements: '.*', description: 'Motivation message for mail to group')]
    #[Rest\RequestParam(name: 'ability', requirements: '.*', description: 'Ability message for mail to group')]
    #[Rest\RequestParam(name: 'experience', requirements: '.*', description: 'Experience message for mail to group')]
    #[Rest\RequestParam(name: 'selectedTime', requirements: '^(1|2|3|5)$', description: 'Selected time message for mail to group')]
    public function sendGroupRequest(int $groupId, ParamFetcher $paramFetcher): Response
    {
        $userId = $this->session->id();
        if (!$userId) {
            throw new UnauthorizedHttpException('');
        }

        $motivation = $paramFetcher->get('motivation');
        $ability = $paramFetcher->get('ability');
        $experience = $paramFetcher->get('experience');
        $selectedTime = $paramFetcher->get('selectedTime');

        $group = $this->workGroupGateway->getGroup($groupId);
        if (!$group) {
            throw new NotFoundHttpException();
        }

        $this->groupTransactions->requestToGroup($groupId, $userId, $motivation, $ability, $experience, $selectedTime);

        return $this->handleView($this->view([], 200));
    }
}
