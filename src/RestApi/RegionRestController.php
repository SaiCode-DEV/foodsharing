<?php

namespace Foodsharing\RestApi;

use Foodsharing\Lib\Session;
use Foodsharing\Modules\Bell\BellGateway;
use Foodsharing\Modules\Bell\DTO\Bell;
use Foodsharing\Modules\Core\DBConstants\Bell\BellType;
use Foodsharing\Modules\Core\DBConstants\Foodsaver\ChangeHistoryKey;
use Foodsharing\Modules\Core\DBConstants\Foodsaver\Role;
use Foodsharing\Modules\Core\DBConstants\Region\WorkgroupFunction;
use Foodsharing\Modules\Core\DBConstants\Unit\UnitType;
use Foodsharing\Modules\Event\EventGateway;
use Foodsharing\Modules\Foodsaver\FoodsaverGateway;
use Foodsharing\Modules\Foodsaver\UnitMember;
use Foodsharing\Modules\Group\GroupFunctionGateway;
use Foodsharing\Modules\Region\DTO\PublicRegionData;
use Foodsharing\Modules\Region\DTO\PublicRegionPatch;
use Foodsharing\Modules\Region\DTO\RegionForTreeNavigation;
use Foodsharing\Modules\Region\DTO\RegionOptions;
use Foodsharing\Modules\Region\DTO\RegionOptionsPatch;
use Foodsharing\Modules\Region\DTO\RegionWithMembership;
use Foodsharing\Modules\Region\ForumFollowerGateway;
use Foodsharing\Modules\Region\RegionGateway;
use Foodsharing\Modules\Region\RegionTransactions;
use Foodsharing\Modules\Settings\SettingsGateway;
use Foodsharing\Modules\Store\DTO\CommonLabel;
use Foodsharing\Modules\Store\StoreGateway;
use Foodsharing\Modules\Unit\CurrentUserUnitsInterface;
use Foodsharing\Modules\WorkGroup\WorkGroupTransactions;
use Foodsharing\Permissions\RegionPermissions;
use Foodsharing\Permissions\WorkGroupPermissions;
use Foodsharing\RestApi\Models\Region\RegionForAdministration;
use Foodsharing\Utility\ImageHelper;
use Nelmio\ApiDocBundle\Annotation\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

#[OA\Tag(name: 'region')]
#[OA\Response(response: Response::HTTP_UNAUTHORIZED, description: 'Login required')]
#[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Not permitted')]
#[OA\Response(response: Response::HTTP_NOT_FOUND, description: 'Requested region does not exist')]
class RegionRestController extends AbstractFoodsharingRestController
{
    public function __construct(
        private readonly SettingsGateway $settingsGateway,
        private readonly BellGateway $bellGateway,
        private readonly FoodsaverGateway $foodsaverGateway,
        private readonly RegionPermissions $regionPermissions,
        private readonly RegionGateway $regionGateway,
        private readonly StoreGateway $storeGateway,
        private readonly ImageHelper $imageHelper,
        private readonly GroupFunctionGateway $groupFunctionGateway,
        private readonly RegionTransactions $regionTransactions,
        private readonly WorkGroupPermissions $workGroupPermissions,
        private readonly WorkGroupTransactions $workGroupTransactions,
        private readonly EventGateway $eventGateway,
        private readonly CurrentUserUnitsInterface $currentUserUnits,
        private readonly ForumFollowerGateway $forumFollowerGateway,
        protected Session $session,
    ) {
    }

    #[OA\Put(summary: 'Join a region.')]
    #[Route('regions/{regionId}/users/current', methods: ['PUT'], requirements: ['regionId' => Requirement::POSITIVE_INT])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success')]
    public function joinRegion(int $regionId): Response
    {
        $this->assertLoggedIn();
        $region = $this->assertRegionExists($regionId);

        if (!$this->regionPermissions->mayJoinRegion($regionId)) {
            throw new AccessDeniedHttpException('Not permitted');
        }

        $sessionId = $this->session->id();

        // Assign the region as home region when the user has none yet. This also
        // covers the "Wechsler" who is already a member but lost their home region
        // (#2771); the permission check above already ruled out non-joinable regions.
        if (!$this->currentUserUnits->getCurrentRegionId()) {
            $this->settingsGateway->logSingleChangedSetting($sessionId, ChangeHistoryKey::JOINED_REGION, 0, $regionId);
            $this->foodsaverGateway->updateProfile($sessionId, ['bezirk_id' => $regionId]);
        }

        // If the user is already in the region, only the home-region assignment above
        // was needed.
        if (in_array($regionId, $this->currentUserUnits->listRegionIDs())) {
            $this->currentUserUnits->clearUnitsInformation();

            return $this->respondOK();
        }

        $this->regionGateway->linkBezirk($sessionId, $regionId);

        // Revoke OAuth refresh tokens to force fresh region claims on next refresh
        $this->foodsaverGateway->revokeOAuthRefreshTokens($sessionId);

        // Clear cached units information in session so the user's
        // regions/home-region are reloaded on next access
        $this->currentUserUnits->clearUnitsInformation();

        $regionWelcomeGroupId = $this->groupFunctionGateway->getRegionFunctionGroupId($regionId, WorkgroupFunction::WELCOME);
        if ($regionWelcomeGroupId) {
            $welcomeBellRecipients = $this->foodsaverGateway->getAdminsOrAmbassadors($regionWelcomeGroupId);
        } else {
            $welcomeBellRecipients = $this->foodsaverGateway->getAdminsOrAmbassadors($regionId);
        }
        $welcomeBellRecipients = array_column($welcomeBellRecipients, 'id');

        $bellData = Bell::create(
            'new_foodsaver_title',
            $this->regionTransactions->getJoinMessage($this->session->id(), $this->session->isVerified()),
            $this->imageHelper->img($this->session->user('photo'), 50),
            ['href' => '/profile/' . (int)$sessionId . ''],
            [
                'name' => $this->session->user('name') . ' ' . $this->session->user('nachname'),
                'bezirk' => $region['name']
            ],
            BellType::createIdentifier(BellType::NEW_FOODSAVER_IN_REGION, $sessionId),
        );
        $this->bellGateway->addBellForUsers($welcomeBellRecipients, $bellData);

        return $this->respondOK();
    }

    #[OA\Delete(summary: 'Removes the current user from a region.')]
    #[Route('regions/{regionId}/users/current', methods: ['DELETE'], requirements: ['regionId' => Requirement::POSITIVE_INT])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success')]
    #[OA\Response(response: Response::HTTP_CONFLICT, description: 'Still a store member in that region')]
    public function leaveRegion(int $regionId): Response
    {
        $this->assertLoggedIn();
        $this->assertRegionExists($regionId);

        $userId = $this->session->id();
        $storeMembership = $this->storeGateway->isStoreMemberInRegion($userId, $regionId);
        if (count($storeMembership) > 0) {
            $ids = implode(', ', $storeMembership);
            throw new ConflictHttpException('still a store member in that region (store IDs: ' . $ids . ')');
        }

        $this->eventGateway->deleteInvitesForFoodSaver($regionId, $userId);
        if ($this->foodsaverGateway->deleteFromRegion($regionId, $userId, $userId)) {
            // Verification status might have changed
            $this->session->invalidateAllSessionsForUser($userId);
        }

        // Clear cached units information in session so the user's regions/home-region
        // are reloaded on next access.
        $this->currentUserUnits->clearUnitsInformation();

        return $this->respondOK();
    }

    #[OA\Patch(summary: 'Sets the options for region.')]
    #[Route('regions/{regionId}/options', methods: ['PATCH'], requirements: ['regionId' => Requirement::POSITIVE_INT])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success')]
    public function setRegionOptions(int $regionId, #[MapRequestPayload] RegionOptionsPatch $options): Response
    {
        $this->assertLoggedIn();
        $this->assertRegionExists($regionId);

        $permissions = $this->regionTransactions->getRegionOptionPermissions($regionId);
        if (!array_any($permissions, fn ($permission) => $permission === true)) {
            throw new AccessDeniedHttpException('Not permitted');
        }
        $this->regionTransactions->patchRegionOptions($regionId, $options);

        return $this->respondOK();
    }

    #[OA\Get(summary: 'Returns the region options for a specific region.')]
    #[Route('regions/{regionId}/options', methods: ['GET'], requirements: ['regionId' => Requirement::POSITIVE_INT])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success', content: new Model(type: RegionOptions::class))]
    public function getRegionOptions(int $regionId): Response
    {
        $this->assertLoggedIn();

        $options = $this->regionGateway->getRegionOptions($regionId);

        if (is_null($options)) {
            throw new NotFoundHttpException('Region does not exist');
        }

        return $this->respondOK($options);
    }

    #[OA\Get(summary: "Returns the user's permissions for setting the region options.")]
    #[Route('regions/{regionId}/options/permissions', requirements: ['regionId' => Requirement::POSITIVE_INT], methods: ['GET'])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success', content: new OA\JsonContent(type: 'object', properties: [
        new OA\Property(property: 'maySetRegionOptionsReportButtons', type: 'boolean'),
        new OA\Property(property: 'maySetRegionOptionsRegionPickupRule', type: 'boolean'),
        new OA\Property(property: 'maySetRegionOptionsUserRelated', type: 'boolean'),
        new OA\Property(property: 'regionPickupRuleActiveStoreList', type: 'array', items: new OA\Items(ref: new Model(type: CommonLabel::class))
        ),
    ]))]
    public function getRegionOptionPermissions(int $regionId): Response
    {
        $this->assertLoggedIn();
        $this->assertRegionExists($regionId);

        $permissions = $this->regionTransactions->getRegionOptionPermissions($regionId);
        $permissions['regionPickupRuleActiveStoreList'] = $this->storeGateway->listRegionStoresActivePickupRule($regionId);

        return $this->respondOK($permissions);
    }

    #[OA\Patch(summary: 'Sets the public data for a region.')]
    #[Route('regions/{regionId}/public', methods: ['PATCH'], requirements: ['regionId' => Requirement::POSITIVE_INT])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success')]
    public function setPublicRegionData(int $regionId, #[MapRequestPayload] PublicRegionPatch $publicRegionPatch): Response
    {
        $this->assertLoggedIn();
        $this->assertRegionExists($regionId);

        if (!$this->regionPermissions->maySetRegionPin($regionId)) {
            throw new AccessDeniedHttpException('Not permitted');
        }

        $this->regionGateway->setRegionPin($regionId, $publicRegionPatch);

        return $this->respondOK();
    }

    #[OA\Get(
        summary: 'Returns a list of all subregions including working groups of a region.',
        description: 'The result is empty if the region does not exist.'
    )]
    #[Route('regions/{regionId}/children', methods: ['GET'], requirements: ['regionId' => Requirement::DIGITS])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success', content: new OA\JsonContent(type: 'array',
        items: new OA\Items(ref: new Model(type: RegionForTreeNavigation::class))
    ))]
    public function listRegionChildren(int $regionId, #[MapQueryParameter] ?bool $includeWorkingGroups): Response
    {
        $this->assertLoggedIn();
        $includeWorkingGroups ??= false;

        if ($includeWorkingGroups && !$this->regionPermissions->mayAccessWorkingGroupList($regionId)) {
            throw new AccessDeniedHttpException('Not permitted');
        }

        $children = $this->regionGateway->getRegionsByParent($regionId, $includeWorkingGroups);

        return $this->respondOK($children);
    }

    #[OA\Get(summary: 'Returns a list of all members for a region.')]
    #[Route('regions/{regionId}/users', methods: ['GET'], requirements: ['regionId' => Requirement::POSITIVE_INT])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success', content: new OA\JsonContent(type: 'array',
        items: new OA\Items(ref: new Model(type: UnitMember::class))
    ))]
    public function listMembers(int $regionId): Response
    {
        $this->assertLoggedIn();

        if (!$this->regionPermissions->maySeeRegionMembers($regionId)) {
            throw new AccessDeniedHttpException('Not permitted');
        }

        $region = $this->regionGateway->getRegion($regionId);
        if ($region['type'] === UnitType::WORKING_GROUP) {
            $maySeeDetails = $this->workGroupPermissions->mayEdit($region);
        } else {
            $maySeeDetails = $this->regionPermissions->mayHandleFoodsaverRegionMenu($regionId);
        }
        $members = $this->foodsaverGateway->listActiveFoodsaversByRegion($regionId, $maySeeDetails);

        return $this->respondOK($members);
    }

    #[OA\Delete(
        summary: 'Removes a member from a region or working group.',
        description: 'If the user was not a member of the region/group, nothing happens.'
    )]
    #[Route('regions/{regionId}/users/{userId}', methods: ['DELETE'], requirements: ['regionId' => Requirement::POSITIVE_INT, 'userId' => Requirement::POSITIVE_INT])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success')]
    #[OA\Response(response: Response::HTTP_CONFLICT, description: 'User is still a store member in that region')]
    public function removeMember(int $regionId, int $userId): Response
    {
        $this->assertLoggedIn();
        $region = $this->assertRegionExists($regionId);

        if (UnitType::isGroup($region['type'])) {
            if (!$this->workGroupPermissions->mayEdit($region)) {
                throw new AccessDeniedHttpException('Not permitted');
            }
            $this->regionGateway->removeRegionAdmin($regionId, $userId);
            $this->workGroupTransactions->removeMemberFromGroup($regionId, $userId);
        } else {
            if (!$this->regionPermissions->mayDeleteFoodsaverFromRegion($regionId)) {
                throw new AccessDeniedHttpException('Not permitted');
            }

            // Disallow removing users which are still store members in that
            // region
            $storeMembership = $this->storeGateway->isStoreMemberInRegion($userId, $regionId);
            if (count($storeMembership) > 0) {
                $ids = implode(', ', $storeMembership);
                throw new ConflictHttpException('user is still a store member in that region (store IDs: ' . $ids . ')');
            }

            if ($this->foodsaverGateway->deleteFromRegion($regionId, $userId, $this->session->id())) {
                // Verification status might have changed
                $this->session->invalidateAllSessionsForUser($userId);
            }
        }

        return $this->respondOK();
    }

    #[OA\Put(summary: 'Sets an user as Admin / Ambassador of a region / workgroup.')]
    #[Route('regions/{regionId}/users/{userId}/admin', methods: ['PUT'], requirements: ['regionId' => Requirement::POSITIVE_INT, 'userId' => Requirement::POSITIVE_INT])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success')]
    public function setAdminOrAmbassador(int $regionId, int $userId): Response
    {
        $this->assertLoggedIn();
        $region = $this->assertRegionExists($regionId);

        if (UnitType::isGroup($region['type'])) {
            if (!$this->workGroupPermissions->mayEdit($region)) {
                throw new AccessDeniedHttpException('Not permitted');
            }
        } else {
            if (!$this->regionPermissions->maySetRegionAdmin()) {
                throw new AccessDeniedHttpException('Not permitted');
            }
            $memberRole = $this->foodsaverGateway->getRole($userId);
            if ($memberRole && $memberRole->isLower(Role::AMBASSADOR)) {
                throw new AccessDeniedHttpException('The user is not permitted not be ambassador');
            }
        }

        $this->regionGateway->setRegionAdmin($regionId, $userId);

        // Revoke OAuth refresh tokens to force fresh region claims on next refresh
        $this->foodsaverGateway->revokeOAuthRefreshTokens($userId);

        return $this->respondOK();
    }

    #[OA\Delete(summary: 'Removes a user as Admin / Ambassador of a region / workgroup.')]
    #[Route('regions/{regionId}/users/{userId}/admin', methods: ['DELETE'], requirements: ['regionId' => Requirement::POSITIVE_INT, 'userId' => Requirement::POSITIVE_INT])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success')]
    public function removeAdminOrAmbassador(int $regionId, int $userId): Response
    {
        $this->assertLoggedIn();
        $region = $this->assertRegionExists($regionId);

        if (UnitType::isGroup($region['type'])) {
            if (!$this->workGroupPermissions->mayEdit($region)) {
                throw new AccessDeniedHttpException('Not permitted');
            }
        } else {
            if (!$this->regionPermissions->mayRemoveRegionAdmin()) {
                throw new AccessDeniedHttpException('Not permitted');
            }
            $this->forumFollowerGateway->deleteForumSubscription($regionId, $userId, 1);
        }

        $this->regionGateway->removeRegionAdmin($regionId, $userId);

        // Revoke OAuth refresh tokens to force fresh region claims on next refresh
        $this->foodsaverGateway->revokeOAuthRefreshTokens($userId);

        return $this->respondOK();
    }

    #[OA\Get(summary: 'Returns the permissions that this user has concerning administration of the members in the region.')]
    #[Route('regions/{regionId}/users/permissions', methods: ['GET'], requirements: ['regionId' => Requirement::POSITIVE_INT])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success', content: new OA\JsonContent(type: 'object', properties: [
        new OA\Property(property: 'mayEditMembers', type: 'boolean'),
        new OA\Property(property: 'maySetAdminOrAmbassador', type: 'boolean'),
        new OA\Property(property: 'mayRemoveAdminOrAmbassador', type: 'boolean'),
    ]))]
    public function getRegionMemberPermissions(int $regionId): Response
    {
        $this->assertLoggedIn();

        $region = $this->regionGateway->getRegion($regionId);
        if (empty($region)) {
            throw new NotFoundHttpException('Region does not exist');
        }

        if ($region['type'] === UnitType::WORKING_GROUP) {
            $mayEditMembers = $this->workGroupPermissions->mayEdit($region);
            $maySetAdminOrAmbassador = $mayEditMembers;
            $mayRemoveAdminOrAmbassador = $mayEditMembers;
        } else {
            $mayEditMembers = $this->regionPermissions->mayDeleteFoodsaverFromRegion((int)$region['id']);
            $maySetAdminOrAmbassador = $this->regionPermissions->maySetRegionAdmin();
            $mayRemoveAdminOrAmbassador = $this->regionPermissions->mayRemoveRegionAdmin();
        }

        $permissions = [
            'mayEditMembers' => $mayEditMembers,
            'maySetAdminOrAmbassador' => $maySetAdminOrAmbassador,
            'mayRemoveAdminOrAmbassador' => $mayRemoveAdminOrAmbassador,
        ];

        return $this->respondOK($permissions);
    }

    #[OA\Get(summary: 'Returns the properties of a specific region.')]
    #[Route('regions/{regionId}', methods: ['GET'], requirements: ['regionId' => Requirement::POSITIVE_INT])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success', content: new Model(type: RegionForAdministration::class))]
    public function getRegion(int $regionId): Response
    {
        $this->assertLoggedIn();
        if (!$this->regionPermissions->mayAdministrateRegions()) {
            throw new AccessDeniedHttpException('Not permitted');
        }

        $region = $this->regionTransactions->getRegionForEditing($regionId);

        return $this->respondOK($region);
    }

    #[OA\Patch(summary: 'Edits the region using the given data.')]
    #[Route('regions/{regionId}', methods: ['PATCH'], requirements: ['regionId' => Requirement::POSITIVE_INT])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success')]
    public function editRegion(int $regionId, #[MapRequestPayload] RegionForAdministration $region)
    {
        $this->assertLoggedIn();
        if (!$this->regionPermissions->mayAdministrateRegions()) {
            throw new AccessDeniedHttpException('Not permitted');
        }
        $region->name = trim($region->name);

        $region->id = $regionId;

        if ($region->type !== UnitType::WORKING_GROUP && $region->workgroupFunction) {
            throw new BadRequestHttpException('Only Working groups can have a workgroup function.');
        }

        if ($region->type === UnitType::WORKING_GROUP) {
            if (!$this->regionPermissions->mayAdministrateWorkgroupFunction($region->workgroupFunction)) {
                throw new AccessDeniedHttpException('You cannot set restricted workgroup functions.');
            }
        }

        $this->regionTransactions->editRegion($region);

        return $this->respondOK();
    }

    #[OA\Post(summary: 'Adds a region using the given data.')]
    #[Route('regions', methods: ['POST'], requirements: ['regionId' => Requirement::POSITIVE_INT])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success', content: new OA\JsonContent(type: 'object', properties: [
        new OA\Property(property: 'regionId', type: 'integer', description: 'The id of the newly created region')
    ]))]
    #[OA\Response(response: Response::HTTP_BAD_REQUEST, description: 'Invalid data')]
    public function addRegion(#[MapRequestPayload] RegionForAdministration $region)
    {
        $this->assertLoggedIn();
        if (!$this->regionPermissions->mayAdministrateRegions()) {
            throw new AccessDeniedHttpException('Not permitted');
        }
        $region->name = trim($region->name);

        if ($region->type !== UnitType::WORKING_GROUP && $region->workgroupFunction) {
            throw new BadRequestHttpException('Only Working groups can have a workgroup function.');
        }
        if ($region->type === UnitType::WORKING_GROUP) {
            if (!$this->regionPermissions->mayAdministrateWorkgroupFunction($region->workgroupFunction)) {
                throw new AccessDeniedHttpException('You cannot set restricted workgroup functions.');
            }
        }

        $regionId = $this->regionTransactions->addRegion($region);

        return $this->respondOK(['regionId' => $regionId]);
    }

    #[OA\Get(summary: 'Returns the public region data.')]
    #[Route('regions/{regionId}/public', requirements: ['regionId' => Requirement::POSITIVE_INT], methods: ['GET'])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success', content: new Model(type: PublicRegionData::class))]
    #[OA\Response(response: Response::HTTP_BAD_REQUEST, description: 'Unavailable for working groups')]
    public function getPublicRegionData(int $regionId)
    {
        $publicRegionData = $this->regionTransactions->getPublicRegionData($regionId);
        if (empty($publicRegionData)) {
            throw new NotFoundHttpException('Region does not exist');
        }
        if ($publicRegionData->type === UnitType::WORKING_GROUP && !$this->regionPermissions->mayViewPublicWorkgroupInfo()) {
            throw new BadRequestHttpException('Unavailable for working groups');
        }

        return $this->respondOK($publicRegionData);
    }

    #[OA\Get(summary: 'Returns the region menu data.')]
    #[Route('regions/{regionId}/menu', requirements: ['regionId' => Requirement::POSITIVE_INT], methods: ['GET'])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success', content: new OA\JsonContent(type: 'object',
        description: 'The data required to display the region or working group menu.', // TODO change to DTO
    ))]
    public function getRegionMenu(int $regionId)
    {
        $this->assertLoggedIn();

        $menu = $this->regionTransactions->getMenu($regionId);
        if (empty($menu)) {
            throw new NotFoundHttpException('Region does not exist');
        }

        return $this->respondOK($menu);
    }

    #[OA\Get(summary: 'Returns all ancestors of a region until the first accessible one (region or group with membership)')]
    #[Route('regions/{regionId}/redirects', requirements: ['regionId' => Requirement::POSITIVE_INT], methods: ['GET'])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success', content: new Model(type: RegionWithMembership::class))]
    public function getInaccessibleRegionRedirects(int $regionId)
    {
        $this->assertLoggedIn();

        $redirects = $this->regionTransactions->getInaccessibleRegionRedirects($regionId, $this->session->id(), true);
        if (empty($redirects)) {
            throw new NotFoundHttpException('Region does not exist');
        }

        return $this->respondOK($redirects);
    }

    private function assertRegionExists(int $regionId): array
    {
        $region = $this->regionGateway->getRegion($regionId);
        if (!$region) {
            throw new NotFoundHttpException('Region does not exist');
        }

        return $region;
    }
}
