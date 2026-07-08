<?php

namespace Foodsharing\Modules\WorkGroup;

use Carbon\Carbon;
use Foodsharing\Lib\Session;
use Foodsharing\Modules\Bell\BellGateway;
use Foodsharing\Modules\Bell\DTO\Bell;
use Foodsharing\Modules\Core\DBConstants\Bell\BellType;
use Foodsharing\Modules\Core\DBConstants\Region\RegionIDs;
use Foodsharing\Modules\Core\DBConstants\Uploads\UploadUsage;
use Foodsharing\Modules\Foodsaver\FoodsaverGateway;
use Foodsharing\Modules\Foodsaver\Profile;
use Foodsharing\Modules\Group\GroupGateway;
use Foodsharing\Modules\Region\ForumFollowerGateway;
use Foodsharing\Modules\Uploads\UploadsGateway;
use Foodsharing\Modules\WorkGroup\DTO\SubGroupEntry;
use Foodsharing\Modules\WorkGroup\DTO\WorkingGroupForListView;
use Foodsharing\Permissions\WorkGroupPermissions;
use Foodsharing\RestApi\DTO\SendGroupRequestData;
use Foodsharing\RestApi\DTO\SendMailData;
use Foodsharing\RestApi\Models\Group\EditWorkGroupData;
use Foodsharing\Utility\EmailHelper;
use Symfony\Contracts\Translation\TranslatorInterface;

class WorkGroupTransactions
{
    public function __construct(
        private readonly WorkGroupGateway $workGroupGateway,
        private readonly ForumFollowerGateway $forumFollowerGateway,
        private readonly UploadsGateway $uploadsGateway,
        private readonly BellGateway $bellGateway,
        private readonly GroupGateway $groupGateway,
        private readonly FoodsaverGateway $foodsaverGateway,
        private readonly WorkGroupPermissions $workGroupPermissions,
        private readonly EmailHelper $emailHelper,
        private readonly TranslatorInterface $translator,
        private readonly Session $session,
    ) {
    }

    /**
     * Removes a user from a working group and cancels the forum subscriptions.
     *
     * @throws \Exception
     */
    public function removeMemberFromGroup(int $groupId, int $memberId): void
    {
        $this->forumFollowerGateway->deleteForumSubscription($groupId, $memberId);
        $this->workGroupGateway->removeFromGroup($groupId, $memberId);
        $this->foodsaverGateway->revokeOAuthRefreshTokens($memberId);
    }

    public function sendMailToGroup(string $groupName, SendMailData $data, string $username, int $userId, array $recipients, string $userMail): void
    {
        $this->emailHelper->tplMail('general/workgroup_contact', $recipients, [
            'gruppenname' => $groupName,
            'message' => $data->message,
            'username' => $username,
            'userprofile' => BASE_URL . '/profile/' . $userId,
        ], $userMail);
    }

    public function requestToGroup(int $groupId, int $userId, SendGroupRequestData $data): void
    {
        $application = trim($data->application);

        $this->workGroupGateway->groupApply($groupId, $userId, $application);
        $groupMail = $this->groupGateway->getGroupMailName($groupId);
        $group = $this->workGroupGateway->getGroup($groupId);

        if ($groupMail) {
            $userWithMail = $this->workGroupGateway->getFsWithMail($userId);

            $link = BASE_URL . '/region?bid=' . $groupId . '&sub=applications&userId=' . $userId;

            $this->emailHelper->libmail(
                [
                    'email' => $userWithMail['email'],
                    'email_name' => $userWithMail['name'],
                ],
                $groupMail . '@' . PLATFORM_MAILBOX_HOST,
                $this->translator->trans('group.apply.title', ['{group}' => $group['name']]),
                nl2br($this->translator->trans('group.apply.summary', [
                        '{name}' => $userWithMail['name'],
                        '{group}' => $group['name'],
                    ]) . "\n\n" . $application . "\n\n"
                    . $this->translator->trans('group.apply.link_description')
                    . ' <a href="' . $link . '">' . $link . '</a>')
            );
        }

        $this->createBellNotificationForRequest($group, $userId);
    }

    /**
     * Create a bell notification for the group's admins about a new request to join the group.
     *
     * @param array $group containing the name and id of the group
     * @param int $userId the user who requested to join the group
     */
    private function createBellNotificationForRequest(array $group, int $userId): void
    {
        $adminIds = $this->workGroupGateway->getGroupAdminIds($group['id']);
        $bellData = Bell::create('workinggroup_new_request_title', 'workinggroup_new_request', 'fas fa-user-plus', [
            'href' => '/region?bid=' . $group['id'] . '&sub=applications&userId=' . $userId
        ], [
            'name' => $group['name']
        ], BellType::createIdentifier(BellType::WORKING_GROUP_NEW_APPLICATION, $group['id'], $userId));
        $this->bellGateway->addBellForUsers($adminIds, $bellData);
    }

    public function updateGroup(int $groupId, EditWorkGroupData $groupData): void
    {
        // Delete the old photo if it was replaced or removed
        $group = $this->workGroupGateway->getGroup($groupId);
        if (!empty($group['photo']) && $group['photo'] !== $groupData->photo) {
            $oldPhoto = substr($group['photo'], 13);
            $this->uploadsGateway->deleteUpload($oldPhoto);
        }

        $this->workGroupGateway->updateGroup($groupId, $groupData);

        if (!empty($groupData->photo)) {
            $uuid = substr($groupData->photo, 13);
            $this->uploadsGateway->setUsage([$uuid], UploadUsage::WORKING_GROUP_TITLE, $groupId);
        }
    }

    public function listGroupsInRegion(int $regionId): array
    {
        ['groups' => $groupsData, 'subGroups' => $subGroups, 'admins' => $admins] = $this->workGroupGateway->fetchDataForGroupList($regionId, $this->session->id());

        // Combine groups with their respective subgroups and admins.
        // This is necessary because the data is fetched in separate queries for better performance.
        $groupsById = [];
        foreach ($groupsData as &$group) {
            $group['subGroups'] = [];
            $group['admins'] = [];
            $groupsById[$group['id']] = &$group;
        }
        foreach ($subGroups as $subGroup) {
            $groupsById[$subGroup['groupId']]['subGroups'][] = $subGroup;
        }
        foreach ($admins as $admin) {
            $groupsById[$admin['groupId']]['admins'][] = $admin;
        }

        // Map the combined data to the DTO for the API response.
        $groups = array_map(function ($data) use ($regionId) {
            $group = new WorkingGroupForListView();
            $group->id = $data['id'];
            $group->name = $data['name'];
            $group->description = $data['teaser'];
            $group->categoryId = $data['category_id'];
            $group->memberCount = $data['memberCount'];
            $group->hasAppliedFor = $data['active'] === 0; // 0 meaning the user applied
            $group->groupFunctionType = $data['function_id'];
            $group->email = $data['email'];
            $group->image = empty($data['photo']) ? null : $data['photo'];
            $group->latestActivity = is_null($data['latest_activity']) ? null : Carbon::parse($data['latest_activity']);
            $group->admins = array_map(fn ($admin) => new Profile($admin['id'], $admin['name'], $admin['photo'], (bool)$admin['is_sleeping']), $data['admins']);
            $group->subGroups = array_map(function ($subGroupData) {
                $subGroup = new SubGroupEntry();
                $subGroup->id = $subGroupData['id'];
                $subGroup->name = $subGroupData['name'];
                $subGroup->email = $subGroupData['email'];
                $subGroup->latestActivity = is_null($subGroupData['latest_activity']) ? null : Carbon::parse($subGroupData['latest_activity']);

                return $subGroup;
            }, $data['subGroups']);

            $applyType = $data['apply_type'];
            $group->mayApply = $this->workGroupPermissions->mayApply($group->id, $regionId, $applyType, $group->hasAppliedFor);
            $group->mayJoin = $this->workGroupPermissions->mayJoin($group->id, $regionId, $applyType);
            $group->mayAccess = $this->workGroupPermissions->mayAccess($group->id, $regionId);
            $group->hasSpecialPermissions = RegionIDs::hasSpecialPermission($group->id);
            $group->applicationPrompt = $group->mayApply ? $data['application_prompt'] : null;

            return $group;
        }, array_values($groupsById));

        return $groups;
    }
}
