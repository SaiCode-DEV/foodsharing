<?php

namespace Foodsharing\Modules\Profile;

use Carbon\Carbon;
use Foodsharing\Lib\FoodsharingController;
use Foodsharing\Modules\Basket\BasketGateway;
use Foodsharing\Modules\Mailbox\MailboxGateway;
use Foodsharing\Modules\Mails\MailsGateway;
use Foodsharing\Modules\Region\RegionGateway;
use Foodsharing\Permissions\ProfilePermissions;
use Foodsharing\Permissions\ReportPermissions;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

final class ProfileController extends FoodsharingController
{
    public function __construct(
        private readonly MailsGateway $mailsGateway,
        private readonly ProfileView $view,
        private readonly RegionGateway $regionGateway,
        private readonly ProfileGateway $profileGateway,
        private readonly BasketGateway $basketGateway,
        private readonly MailboxGateway $mailboxGateway,
        private readonly ReportPermissions $reportPermissions,
        private readonly ProfilePermissions $profilePermissions,
    ) {
        parent::__construct();
    }

    #[Route('/profile', name: 'profile_fallback')]
    #[Route('/profile/{userId}', name: 'profile_id', requirements: ['userId' => Requirement::DIGITS])]
    #[Route('/user/{userId}/profile', name: 'user_profile', requirements: ['userId' => Requirement::DIGITS])]
    public function byId(?int $userId): Response
    {
        if (empty($userId)) {
            $userId = $this->session->id();
        }

        if (!$this->session->mayRole()) {
            return $this->redirectToRoute('user_profile_public', ['userId' => $userId]);
        }

        $foodsaver = $this->createUserArray($userId);

        $fsId = $foodsaver['id'];

        $wallPosts = $this->view->vueComponent('vue-wall', 'wall', [
            'target' => 'foodsaver',
            'targetId' => $fsId,
            'title' => $this->translator->trans('profile.pinboard', ['{name}' => $foodsaver['name']]),
            'galleryHeightInPx' => 250,
        ]);
        $userStores = $this->profileGateway->listStoresOfFoodsaver($fsId);

        $profileCommitmentsStat = $this->getProfileCommitmentsStat($fsId);

        $this->view->profile($wallPosts, $userStores, $profileCommitmentsStat);

        return $this->renderGlobal();
    }

    #[Route('/profile/{userId}/public', name: 'profile_id_public', requirements: ['userId' => Requirement::DIGITS])]
    #[Route('/user/{userId}/profile/public', name: 'user_profile_public', requirements: ['userId' => Requirement::DIGITS])]
    public function profilePublic(int $userId): Response
    {
        $viewerId = $this->session->id() ?? -1; // -1 carries special meaning for `profileGateway:getData`
        $userArray = $this->profileGateway->getData($userId, $viewerId, $this->reportPermissions->mayHandleReports());

        $isVerified = $userArray['verified'] ?? 0;
        $initials = mb_substr($userArray['name'] ?? '?', 0, 1) . '.';
        $regionId = $userArray['bezirk_id'] ?? null;
        $regionName = ($regionId === null) ? '?' : $this->regionGateway->getRegionName($regionId);
        $this->pageHelper->addContent(
            $this->view->vueComponent('profile-public', 'PublicProfile', [
                'canPickUp' => $isVerified > 0,
                'fromRegion' => $regionName,
                'fsId' => $userArray['id'] ?? '',
                'initials' => $initials,
            ])
        );

        return $this->renderGlobal();
    }

    private function createUserArray(int $userId): array
    {
        $viewerId = $this->session->id() ?? -1; // -1 carries special meaning for `profileGateway:getData`
        $userArray = $this->profileGateway->getData($userId, $viewerId, $this->reportPermissions->mayHandleReports());

        $isRemoved = (!$userArray) || isset($userArray['deleted_at']);
        if ($isRemoved) {
            $this->flashMessageHelper->error($this->translator->trans('profile.notFound'));
            $this->routeHelper->goPageAndExit('dashboard');
        }

        $userArray['buddy'] = $this->profileGateway->buddyStatus($userId, $viewerId);
        if ($this->profilePermissions->maySeeBounceWarning($userId)) {
            $emailIsBouncing = $this->mailsGateway->emailIsBouncing($userArray['email']);
            $userArray['emailIsBouncing'] = $emailIsBouncing;
            if ($this->profilePermissions->mayRemoveFromBounceList($userId)) {
                $userArray['emailBounceCategories'] = $this->mailsGateway->getBounces($userArray['email']);
            }
        }
        $userArray['basketCount'] = $this->basketGateway->getAmountOfFoodBaskets($userId);
        if ((int)$userArray['mailbox_id'] > 0 && $this->profilePermissions->maySeeEmailAddress($userId)) {
            $mailbox = $this->mailboxGateway->getMailboxname($userArray['mailbox_id']) . '@' . PLATFORM_MAILBOX_HOST;
            $userArray['mailbox'] = $mailbox;
        }

        $this->view->setData($userArray);

        return $userArray;
    }

    private function getProfileCommitmentsStat(int $fsId): array
    {
        $maySeeCommitmentsStat = $this->profilePermissions->maySeeCommitmentsStat($fsId);
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

        return $profileCommitmentsStat;
    }

    #[Route('/profile/{userId}/notes', name: 'profile_id_notes', requirements: ['userId' => Requirement::DIGITS])]
    public function orgaTeamNotes(int $userId): Response
    {
        if (!$this->session->mayRole()) {
            return $this->redirectToRoute('user_profile_public', ['userId' => $userId]);
        }

        $foodsaver = $this->createUserArray($userId);

        $fsId = $foodsaver['id'];
        $this->pageHelper->addBread($foodsaver['name'], '/profile/' . $fsId);
        if ($this->profilePermissions->maySeeUserNotes($fsId)) {
            $wallPosts = $this->view->vueComponent('vue-wall', 'wall', [
                'target' => 'usernotes',
                'targetId' => $fsId,
                'title' => $this->translator->trans('profile.notes.title', ['{name}' => $foodsaver['name']]),
            ]);
            $this->view->userNotes($wallPosts);
        } else {
            $this->routeHelper->goAndExit('/profile/' . $fsId);
        }

        return $this->renderGlobal();
    }
}
