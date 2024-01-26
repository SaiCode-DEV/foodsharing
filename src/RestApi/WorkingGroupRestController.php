<?php

namespace Foodsharing\RestApi;

use Foodsharing\Lib\Session;
use Foodsharing\Modules\Core\DBConstants\Unit\UnitType;
use Foodsharing\Modules\Foodsaver\FoodsaverGateway;
use Foodsharing\Modules\Foodsaver\Profile;
use Foodsharing\Modules\WorkGroup\WorkGroupGateway;
use Foodsharing\Permissions\WorkGroupPermissions;
use Foodsharing\RestApi\Models\Group\EditWorkGroupData;
use FOS\RestBundle\Controller\AbstractFOSRestController;
use FOS\RestBundle\Controller\Annotations as Rest;
use Nelmio\ApiDocBundle\Annotation\Model;
use OpenApi\Annotations as OA;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class WorkingGroupRestController extends AbstractFOSRestController
{
    private WorkGroupGateway $workGroupGateway;
    private FoodsaverGateway $foodsaverGateway;
    private Session $session;
    private WorkGroupPermissions $workGroupPermissions;

    public function __construct(
        WorkGroupGateway $workGroupGateway,
        FoodsaverGateway $foodsaverGateway,
        Session $session,
        WorkGroupPermissions $workGroupPermissions,
    ) {
        $this->workGroupGateway = $workGroupGateway;
        $this->foodsaverGateway = $foodsaverGateway;
        $this->session = $session;
        $this->workGroupPermissions = $workGroupPermissions;
    }

    /**
     * Adds a member to a working group. If the user is already a member of the group, nothing happens.
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

        if (!$this->workGroupPermissions->mayEdit($group)) {
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

        $this->workGroupGateway->updateGroup($groupId, $groupData);

        return $this->handleView($this->view($groupData, 200));
    }
}
