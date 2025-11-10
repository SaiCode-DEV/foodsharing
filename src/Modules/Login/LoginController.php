<?php

namespace Foodsharing\Modules\Login;

use Foodsharing\Lib\FoodsharingController;
use Foodsharing\Modules\Settings\SettingsGateway;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class LoginController extends FoodsharingController
{
    public function __construct(
        private readonly LoginGateway $loginGateway,
        private readonly SettingsGateway $settingsGateway,
        private readonly LoginService $loginService
    ) {
        parent::__construct();
    }

    #[Route('/login', name: 'login')]
    public function index(Request $request): Response
    {
        $sub = $request->query->get('sub');

        // unsubscribe and activate are the only methods here that make sense when logged in
        // activate is also used to validate a changed email address for existing foodsharers
        // unsubscribe
        if ($this->session->mayRole() && !in_array($sub, ['unsubscribe', 'activate', 'resendActivationMail'])) {
            return $this->redirectToRoute('dashboard');
        }

        // not logged in here
        return match ($sub) {
            'unsubscribe' => $this->unsubscribe($request),
            'resendActivationMail' => $this->resendActivationMail(),
            'activate' => $this->activate($request),
            default => $this->loginPage(),
        };
    }

    #[Route('/password-reset/{token}', name: 'password_reset_with_token', requirements: ['token' => '[a-f0-9]+'])]
    #[Route('/password-reset', name: 'password_reset_direct')]
    public function passwordReset(string $token = ''): Response
    {
        $this->session->set('isLoggedIn', false);

        if ($this->session->mayRole()) {
            return $this->redirectToRoute('dashboard');
        }

        if (!empty($token)) {
            // Reset password with token
            $this->pageHelper->addTitle($this->translator->trans('register.set-password'));
            $this->pageHelper->addBread($this->translator->trans('register.set-password'));
            $vue = $this->prepareVueComponent('reset-password-with-token-page', 'ResetPasswordWithTokenPage', ['token' => $token]);
        } else {
            // Request password reset
            $this->pageHelper->addTitle($this->translator->trans('password.reset'));
            $this->pageHelper->addBread($this->translator->trans('password.reset'));
            $vue = $this->prepareVueComponent('forgot-password-page', 'ForgotPasswordPage');
        }

        $this->pageHelper->addContent($vue);

        return $this->renderGlobal();
    }

    private function loginPage(): Response
    {
        $vue = $this->prepareVueComponent('login-page', 'LoginPage');
        $this->pageHelper->addContent($vue);

        return $this->renderGlobal();
    }

    private function unsubscribe(Request $request): Response
    {
        $this->pageHelper->addTitle($this->translator->trans('logincontrol.title'));
        $this->pageHelper->addBread($this->translator->trans('logincontrol.bread'));

        if (!$request->query->has('e')) {
            return $this->renderGlobal();
        }

        $email = $request->query->get('e');
        $token = $request->query->get('t');
        if ($this->emailHelper->validEmail($email)) {
            $this->settingsGateway->unsubscribeNewsletter($email, $token);
            $this->pageHelper->addContent($this->v_utils->v_info($this->translator->trans('logincontrol.nomorenewsletter'), $this->translator->trans('logincontrol.success')));
        }

        return $this->renderGlobal();
    }

    private function resendActivationMail(): Response
    {
        $fsId = $this->session->id();

        if (is_null($fsId)) {
            return $this->redirectToRoute('login');
        }

        if ($this->loginService->newMailActivation($fsId)) {
            $this->flashMessageHelper->info($this->translator->trans('dashboard.activation_mail_sent'));
        } else {
            $this->flashMessageHelper->error($this->translator->trans('dashboard.activation_mail_failure'));
        }

        return $this->redirectToRoute('dashboard');
    }

    private function activate(Request $request): Response
    {
        if ($request->query->has('e') && $request->query->has('t')
            && $this->loginGateway->activate($request->query->get('e'), $request->query->get('t'))) {
            $this->flashMessageHelper->success($this->translator->trans('register.activation_success'));
        } else {
            $this->flashMessageHelper->error($this->translator->trans('register.activation_failed'));
        }

        return $this->redirectToRoute('login');
    }
}
