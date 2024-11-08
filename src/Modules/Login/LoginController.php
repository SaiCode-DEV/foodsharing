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
        private readonly LoginView $view,
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
        if ($this->session->mayRole() && !in_array($sub, ['unsubscribe', 'activate'])) {
            return $this->redirectToRoute('dashboard');
        }

        // not logged in here
        return match ($sub) {
            'unsubscribe' => $this->unsubscribe($request),
            'resendActivationMail' => $this->resendActivationMail(),
            'activate' => $this->activate($request),
            'passwordReset' => $this->passwordReset($request),
            default => $this->loginPage(),
        };
    }

    #[Route('/recovery', name: 'recovery')]
    public function recovery(Request $request): Response
    {
        if ($this->session->mayRole()) {
            return $this->redirectToRoute('dashboard');
        }

        return $this->passwordReset($request);
    }

    private function loginPage(): Response
    {
        $this->pageHelper->addContent($this->view->loginPage());

        return $this->renderGlobal();
    }

    private function unsubscribe(Request $request): Response
    {
        $this->pageHelper->addTitle($this->translator->trans('logincontrol.title'));
        $this->pageHelper->addBread($this->translator->trans('logincontrol.bread'));

        if (!$request->query->has('e')) {
            return $this->renderGlobal();
        }

        $e = $request->query->get('e');
        if ($this->emailHelper->validEmail($e)) {
            $this->settingsGateway->unsubscribeNewsletter($e);
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

    private function passwordReset(Request $request): Response
    {
        $k = false;

        if ($request->query->has('k')) {
            $k = strip_tags((string)$request->query->get('k'));
        }

        $this->pageHelper->addTitle($this->translator->trans('login.pwreset.bread'));
        $this->pageHelper->addBread($this->translator->trans('login.pwreset.bread'));

        if ($request->request->has('email') || $request->query->has('m')) {
            $mail = '';
            if ($request->query->has('m')) {
                $mail = $request->query->get('m');
            } else {
                $mail = $request->request->get('email');
            }
            if (!$this->emailHelper->validEmail($mail)) {
                $this->flashMessageHelper->error($this->translator->trans('login.pwreset.wrongMail'));
            } else {
                if ($this->loginGateway->addPassRequest($mail)) {
                    $this->flashMessageHelper->info($this->translator->trans('login.pwreset.mailSent'));
                } else {
                    $this->flashMessageHelper->error($this->translator->trans('login.pwreset.wrongMail'));
                }
            }
        }

        if ($k === false) {
            $this->pageHelper->addContent($this->view->passwordRequest($request->getRequestUri()));

            return $this->renderGlobal();
        }

        if (!$this->loginGateway->checkResetKey($k)) {
            $this->flashMessageHelper->error($this->translator->trans('login.pwreset.expired'));
            $this->pageHelper->addContent($this->view->passwordRequest($request->getRequestUri()), CNT_LEFT);

            return $this->renderGlobal();
        }

        if ($request->request->has('pass1') && $request->request->has('pass2')) {
            $pass1 = $request->request->get('pass1');
            $pass2 = $request->request->get('pass2');
            if ($pass1 === $pass2) {
                $check = true;
                if ($this->loginGateway->newPassword($request->request->all())) {
                    $this->flashMessageHelper->success(
                        $this->translator->trans('login.pwreset.success')
                    );
                } elseif (strlen((string)$pass1) < 5) {
                    $check = false;
                    $this->flashMessageHelper->error($this->translator->trans('login.pwreset.tooShort'));
                } else {
                    $check = false;
                    $this->flashMessageHelper->error($this->translator->trans('login.pwreset.error'));
                }

                if ($check) {
                    return $this->redirectToRoute('login');
                }
            } else {
                $this->flashMessageHelper->error($this->translator->trans('login.pwreset.mismatch'));
            }
        }
        $this->pageHelper->addJs('$("#pass1").val("");');
        $this->pageHelper->addContent($this->view->newPasswordForm($k));

        return $this->renderGlobal();
    }
}
