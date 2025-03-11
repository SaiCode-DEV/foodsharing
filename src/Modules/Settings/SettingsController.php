<?php

namespace Foodsharing\Modules\Settings;

use Exception;
use Foodsharing\Lib\FoodsharingController;
use Foodsharing\Modules\BusinessCard\BusinessCardGateway;
use Foodsharing\Modules\Core\DBConstants\Foodsaver\Role;
use Foodsharing\Modules\Foodsaver\FoodsaverGateway;
use Foodsharing\Modules\Region\RegionGateway;
use Foodsharing\Modules\Unit\UnitGateway;
use Foodsharing\Permissions\SettingsPermissions;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class SettingsController extends FoodsharingController
{
    public function __construct(
        private readonly SettingsGateway $settingsGateway,
        private readonly FoodsaverGateway $foodsaverGateway,
        private readonly BusinessCardGateway $businessCardGateway,
        private readonly SettingsPermissions $settingsPermissions,
        private readonly RegionGateway $regionGateway,
        private readonly UnitGateway $unitGateway
    ) {
        parent::__construct();

        $sessionUserId = $this->session->id();
        if (!$sessionUserId) {
            $this->routeHelper->goLoginAndExit();
        }
    }

    #[Route('/user/current/settings', name: 'current_user_settings')]
    public function currentUserSettings(Request $request): RedirectResponse
    {
        $queryParameters = $request->query->all();
        $queryParameters['userId'] = $this->session->id();

        return $this->redirectToRoute('user_settings', $queryParameters);
    }

    /**
     * This route is called from the legal page if the user does not want to accept the privacy policy. Only the
     * sub-page for account deletion is shown.
     */
    #[Route('/user/current/deleteaccount', name: 'delete_account')]
    public function deleteAccount(): Response
    {
        $this->pageHelper->addContent($this->prepareVueComponent('delete-account-page', 'DeleteAccountPage', [
            'userId' => $this->session->id()
        ]));

        return $this->renderGlobal();
    }

    /**
     * Handles the user settings page.
     *
     * This method retrieves and displays the user settings for the specified user ID.
     * It checks the session user's role and permissions, retrieves user details,
     * and prepares the necessary parameters for rendering the settings page.
     *
     * @param int $userId the ID of the user whose settings are being accessed
     * @param Request $request the current HTTP request
     * @return Response a Response instance for rendering the user settings page
     * @throws Exception
     *
     * ToDo: Fetch data via REST API and use DTOs for data transfer
     */
    #[Route('/user/{userId}/settings', name: 'user_settings')]
    public function userSettings(int $userId, Request $request): Response
    {
        $sessionUserId = $this->session->id();

        $this->pageHelper->addBread($this->translator->trans('foodsaver.profileBack'), '/user/' . $userId . '/profile');
        $this->pageHelper->addBread($this->translator->trans('settings.title'));

        $params['subPage'] = $request->query->get('sub', null);

        $params['sleepingData'] = null;
        $targetRole = $this->getNextTargetRole();

        if ($this->session->role()->value < $targetRole->value) {
            $params['targetRole'] = $targetRole->value;
        }

        $userDetails = $this->foodsaverGateway->getFoodsaverDetails($userId);

        if (empty($userDetails)) {
            $this->flashMessageHelper->error($this->translator->trans('profile.notFound'));

            return $this->redirect('/');
        }

        if ($this->settingsPermissions->mayEditProfileSettings($userId)) {
            $userDetails['lat'] = is_null($userDetails['lat']) ? null : (float)$userDetails['lat'];
            $userDetails['lon'] = is_null($userDetails['lon']) ? null : (float)$userDetails['lon'];
            $params['userDetails'] = $userDetails;
            $params['userDetails']['homeRegionName'] = $params['userDetails']['bezirk_id'] !== null ? $this->regionGateway->getRegionName($params['userDetails']['bezirk_id']) : null;
            $params['permissions']['isOnTeamPage'] = $this->unitGateway->isUserOnTeamPage($userId);
            $params['permissions']['mayChangeVerifiedData'] = $this->settingsPermissions->mayChangeVerifiedData($userId);
            $params['permissions']['mayChangeEmailImmediately'] = $this->settingsPermissions->mayChangeLoginEmail($userId);
        }

        $isMe = $userId === $sessionUserId;
        if ($isMe) {
            $params['sleepingData'] = $this->settingsGateway->getSleepData($userId);
            $params['businessCardData'] = $this->businessCardGateway->getMyData($userId, $this->session->mayRole(Role::STORE_MANAGER));
        } else {
            $params['userDetails']['token'] = null;
            $params['userDetails']['privacy_policy_accepted_date'] = null;
            $params['userDetails']['privacy_notice_accepted_date'] = null;
            $params['userDetails']['last_activity'] = null;
        }

        $profileSettings = $this->prepareVueComponent('profile-settings-page', 'ProfileSettingsPage', $params);
        $this->pageHelper->addContent($profileSettings);

        return $this->renderGlobal();
    }

    private function getNextTargetRole(): Role
    {
        $currentRole = $this->session->role();
        $targetRole = Role::AMBASSADOR;
        if ($currentRole->value < $targetRole->value - 1) {
            $targetRole = Role::from($currentRole->value + 1);
        }
        if (!$this->session->isVerified()) {
            $targetRole = Role::FOODSAVER;
        }

        return $targetRole;
    }
}
