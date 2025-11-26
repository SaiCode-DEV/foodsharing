<?php

namespace Foodsharing\Modules\Settings;

use Exception;
use Foodsharing\Lib\FoodsharingController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class SettingsController extends FoodsharingController
{
    public function __construct(
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
     */
    #[Route('/user/{userId}/settings', name: 'user_settings')]
    public function userSettings(int $userId, Request $request): Response
    {
        $this->pageHelper->addBread($this->translator->trans('foodsaver.profileBack'), '/user/' . $userId . '/profile');
        $this->pageHelper->addBread($this->translator->trans('settings.title'));

        $params['subPage'] = $request->query->get('sub', null);

        $profileSettings = $this->prepareVueComponent('profile-settings-page', 'ProfileSettingsPage', $params);
        $this->pageHelper->addContent($profileSettings);

        return $this->renderGlobal();
    }
}
