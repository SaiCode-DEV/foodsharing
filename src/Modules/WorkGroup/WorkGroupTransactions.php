<?php

namespace Foodsharing\Modules\WorkGroup;

use Foodsharing\Modules\Bell\BellGateway;
use Foodsharing\Modules\Bell\DTO\Bell;
use Foodsharing\Modules\Core\DBConstants\Bell\BellType;
use Foodsharing\Modules\Core\DBConstants\Uploads\UploadUsage;
use Foodsharing\Modules\Group\GroupGateway;
use Foodsharing\Modules\Region\ForumFollowerGateway;
use Foodsharing\Modules\Uploads\UploadsGateway;
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
        private readonly EmailHelper $emailHelper,
        private readonly TranslatorInterface $translator
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
        $content = [
            $this->translator->trans('group.entermotivation') . "\n===========\n" . trim($data->motivation),
            $this->translator->trans('group.enterskills') . "\n============\n" . trim($data->ability),
            $this->translator->trans('group.enterxp') . "\n==========\n" . trim($data->experience),
            $this->translator->trans('group.entertime') . "\n=====\n" . $data->selectedTime,
        ];

        $this->workGroupGateway->groupApply($groupId, $userId, implode("\n\n", $content));
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
                    ]) . "\n\n" . implode("\n\n", $content) . "\n\n"
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
            'href' => '/regions/' . $group['id'] . '/applications/' . $userId
        ], [
            'name' => $group['name']
        ], BellType::createIdentifier(BellType::WORKING_GROUP_NEW_APPLICATION, $group['id'], $userId));
        $this->bellGateway->addBell($adminIds, $bellData);
    }

    public function updateGroup(int $groupId, EditWorkGroupData $groupData): void
    {
        $this->workGroupGateway->updateGroup($groupId, $groupData);

        if (!empty($groupData->photo)) {
            $uuid = substr($groupData->photo, 13);
            $this->uploadsGateway->setUsage([$uuid], UploadUsage::WORKING_GROUP_TITLE, $groupId);
        }
    }
}
