<?php

namespace Foodsharing\Modules\EMailVerify;

use Foodsharing\Lib\FoodsharingController;
use Foodsharing\Modules\Core\DatabaseNoValueFoundException;
use Foodsharing\Modules\Login\LoginGateway;
use Foodsharing\Modules\Settings\SettingsTransactions;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class EMailVerifyController extends FoodsharingController
{
    public function __construct(
        private readonly SettingsTransactions $settingsTransactions,
        private readonly LoginGateway $loginGateway
    ) {
        parent::__construct();
    }

    #[Route('/user/current/settings/email/verify', name: 'new_user_email_verify')]
    public function emailVerify(Request $request): Response
    {
        $token = $request->query->get('token', '');

        try {
            $this->settingsTransactions->verifyAndCompleteEMailChange($token);
            $this->pageHelper->addContent($this->prepareEMailVerificationPage(true, false));
        } catch (DatabaseNoValueFoundException) {
            $this->pageHelper->addContent($this->prepareEMailVerificationPage(false, false));
        }

        return $this->renderGlobal();
    }

    #[Route('/user/current/settings/email/verifyAbort', name: 'abort_email_verify')]
    public function emailCancelVerify(Request $request): Response
    {
        $token = $request->query->get('token', '');

        try {
            $this->settingsTransactions->abortEMailChange($token);
            $this->pageHelper->addContent($this->prepareEMailVerificationPage(false, true));
        } catch (DatabaseNoValueFoundException) {
            $this->pageHelper->addContent($this->prepareEMailVerificationPage(false, false));
        }

        return $this->renderGlobal();
    }

    private function prepareEMailVerificationPage(bool $verified, bool $cancel)
    {
        return $this->prepareVueComponent('email-verification-page', 'EmailVerificationPage', ['verified' => $verified, 'cancel' => $cancel]);
    }

    /**
     * Route for the public page that allows users to request a new verification email.
     */
    #[Route('/emailverification', name: 'verification')]
    public function verification(): Response
    {
        if ($this->session->mayRole() && $this->loginGateway->isActivated($this->session->id())) {
            return $this->redirectToRoute('dashboard');
        }

        $this->pageHelper->addContent($this->prepareVueComponent('resend-email-verification-form', 'ResendEmailVerificationForm'));

        return $this->renderGlobal();
    }
}
