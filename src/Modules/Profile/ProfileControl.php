<?php

namespace Foodsharing\Modules\Profile;

use Carbon\Carbon;
use Foodsharing\Modules\Basket\BasketGateway;
use Foodsharing\Modules\Core\Control;
use Foodsharing\Modules\Mailbox\MailboxGateway;
use Foodsharing\Modules\Mails\MailsGateway;
use Foodsharing\Modules\Region\RegionGateway;
use Foodsharing\Permissions\ProfilePermissions;
use Foodsharing\Permissions\ReportPermissions;
use Foodsharing\Utility\UriHelper;

final class ProfileControl extends Control
{
    private array $foodsaver;
    private readonly MailsGateway $mailsGateway;
    private readonly RegionGateway $regionGateway;
    private readonly ProfileGateway $profileGateway;
    private readonly BasketGateway $basketGateway;
    private readonly MailboxGateway $mailboxGateway;
    private readonly ReportPermissions $reportPermissions;
    private readonly ProfilePermissions $profilePermissions;

    public function __construct(
        MailsGateway $mailsGateway,
        ProfileView $view,
        RegionGateway $regionGateway,
        ProfileGateway $profileGateway,
        BasketGateway $basketGateway,
        MailboxGateway $mailboxGateway,
        ReportPermissions $reportPermissions,
        ProfilePermissions $profilePermissions,
        private readonly UriHelper $uriHelper,
    ) {
        $this->view = $view;
        $this->mailsGateway = $mailsGateway;
        $this->regionGateway = $regionGateway;
        $this->profileGateway = $profileGateway;
        $this->basketGateway = $basketGateway;
        $this->mailboxGateway = $mailboxGateway;
        $this->reportPermissions = $reportPermissions;
        $this->profilePermissions = $profilePermissions;

        parent::__construct();

        if (!$profileId = $this->uriHelper->uriInt(2)) {
            $this->routeHelper->goPageAndExit('dashboard');
        }

        $viewerId = $this->session->id() ?? -1; // -1 carries special meaning for `profileGateway:getData`
        $data = $this->profileGateway->getData($profileId, $viewerId, $this->reportPermissions->mayHandleReports());
        $isRemoved = (!$data) || isset($data['deleted_at']);

        if (!$this->session->mayRole()) {
            $this->profilePublic($data);

            return;
        }

        if ($isRemoved) {
            $this->flashMessageHelper->error($this->translator->trans('profile.notFound'));
            $this->routeHelper->goPageAndExit('dashboard');
        }

        $this->foodsaver = $data;
        $this->foodsaver['buddy'] = $this->profileGateway->buddyStatus($this->foodsaver['id'], $viewerId);
        if ($this->profilePermissions->maySeeBounceWarning($profileId)) {
            $this->foodsaver['emailIsBouncing'] = $this->mailsGateway->emailIsBouncing($this->foodsaver['email']);
            if ($this->profilePermissions->mayRemoveFromBounceList($profileId)) {
                $this->foodsaver['emailBounceCategories'] = $this->mailsGateway->getBounces($this->foodsaver['email']);
            }
        }
        $this->foodsaver['basketCount'] = $this->basketGateway->getAmountOfFoodBaskets($this->foodsaver['id']);
        if ((int)$this->foodsaver['mailbox_id'] > 0 && $this->profilePermissions->maySeeEmailAddress($profileId)) {
            $this->foodsaver['mailbox'] = $this->mailboxGateway->getMailboxname($this->foodsaver['mailbox_id'])
                . '@' . PLATFORM_MAILBOX_HOST;
        }

        $this->view->setData($this->foodsaver);

        // FOODSHARING/profile/12345/ | FOODSHARING/profile/12345/notes
        // ^uriStr(0)  ^(1)    ^(2)   | ^uriStr(0)  ^(1)    ^(2)  ^(3)
        $subpage = $this->uriHelper->uriStr(3);

        if ($subpage === 'notes') {
            $this->orgaTeamNotes();
        } else {
            $this->profile();
        }
    }

    private function orgaTeamNotes(): void
    {
        $fsId = $this->foodsaver['id'];
        $this->pageHelper->addBread($this->foodsaver['name'], '/profile/' . $fsId);
        if ($this->profilePermissions->maySeeUserNotes($fsId)) {
            $wallPosts = $this->view->vueComponent('vue-wall', 'wall', [
                'target' => 'usernotes',
                'targetId' => $fsId,
                'title' => $this->translator->trans('profile.notes.title', ['{name}' => $this->foodsaver['name']]),
            ]);
            $this->view->userNotes($wallPosts);
        } else {
            $this->routeHelper->goAndExit('/profile/' . $fsId);
        }
    }

    public function profile(): void
    {
        $fsId = $this->foodsaver['id'];

        $maySeeStores = $this->profilePermissions->maySeeStores($fsId);
        $maySeeCommitmentsStat = $this->profilePermissions->maySeeCommitmentsStat($fsId);

        $wallPosts = $this->view->vueComponent('vue-wall', 'wall', [
            'target' => 'foodsaver',
            'targetId' => $fsId,
            'title' => $this->translator->trans('profile.pinboard', ['{name}' => $this->foodsaver['name']]),
            'galleryHeightInPx' => 250,
        ]);
        $userStores = $this->profileGateway->listStoresOfFoodsaver($fsId);

        $profileCommitmentsStat[0]['respActStores'] = $maySeeCommitmentsStat ? $this->profileGateway->getResponsibleActiveStoresCount($fsId) : 0;
        $pos = 0;
        for ($i = 2; $i >= -2; --$i) {
            $date = Carbon::now()->addWeeks($i);
            $profileCommitmentsStat[$pos]['beginWeek'] = $date->startOfWeek()->format('d.m.y');
            $profileCommitmentsStat[$pos]['endWeek'] = $date->endOfWeek()->format('d.m.y');
            $profileCommitmentsStat[$pos]['week'] = $date->isoWeek();
            $profileCommitmentsStat[$pos]['data'] = $maySeeCommitmentsStat ? $this->profileGateway->getPickupsStat($fsId, $i) : [];
            $profileCommitmentsStat[$pos]['eventsCreated'] = $maySeeCommitmentsStat ? $this->profileGateway->getEventsCreatedCount($fsId, $i) : 0;
            $profileCommitmentsStat[$pos]['eventsParticipated'] = $maySeeCommitmentsStat ? $this->profileGateway->getEventsParticipatedCount($fsId, $i) : [];
            $profileCommitmentsStat[$pos]['baskets']['offered'] = $maySeeCommitmentsStat ? $this->profileGateway->getBasketsOfferedStat($fsId, $i) : [];
            if ($i <= 0) {
                $profileCommitmentsStat[$pos]['securePickupWeek'] = $maySeeCommitmentsStat ? $this->profileGateway->getSecuredPickupsCount($fsId, $i) : 0;
                $profileCommitmentsStat[$pos]['baskets']['shared'] = $maySeeCommitmentsStat ? $this->profileGateway->getBasketsShared($fsId, $i) : 0;
            }
            ++$pos;
        }

        $this->view->profile($wallPosts, $userStores, $profileCommitmentsStat);
    }

    private function profilePublic(array $profileData): void
    {
        $isVerified = $profileData['verified'] ?? 0;
        $initials = mb_substr($profileData['name'] ?? '?', 0, 1) . '.';
        $regionId = $profileData['bezirk_id'] ?? null;
        $regionName = ($regionId === null) ? '?' : $this->regionGateway->getRegionName($regionId);
        $this->pageHelper->addContent(
            $this->view->vueComponent('profile-public', 'PublicProfile', [
                'canPickUp' => $isVerified > 0,
                'fromRegion' => $regionName,
                'fsId' => $profileData['id'] ?? '',
                'initials' => $initials,
            ])
        );
    }

    // this is required even if empty.
    public function index(): void
    {
    }
}
