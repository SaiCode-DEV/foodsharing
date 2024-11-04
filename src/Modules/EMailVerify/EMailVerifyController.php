<?php

namespace Foodsharing\Modules\EMailVerify;

use Foodsharing\Lib\FoodsharingController;
use Foodsharing\Modules\Core\DatabaseNoValueFoundException;
use Foodsharing\Modules\Settings\SettingsTransactions;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class EMailVerifyController extends FoodsharingController
{
    public function __construct(
        private readonly SettingsTransactions $settingsTransactions
    ) {
        parent::__construct();

        $sessionUserId = $this->session->id();
        if (!$sessionUserId) {
            $this->routeHelper->goLoginAndExit();
        }
    }

    #[Route('/user/current/settings/email/verify', name: 'new_user_email_verify')]
    public function emailVerify(Request $request): Response
    {
        $token = $request->query->get('token', '');

        try {
            $this->settingsTransactions->verifyAndCompleteEMailChange($token);
            $this->pageHelper->addContent($this->prepareEMailVerificationPage(true, false));
        } catch (DatabaseNoValueFoundException $ex) {
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
        } catch (DatabaseNoValueFoundException $ex) {
            $this->pageHelper->addContent($this->prepareEMailVerificationPage(false, false));
        }

        return $this->renderGlobal();
    }

    public function prepareEMailVerificationPage(bool $verified, bool $cancel)
    {
        return $this->prepareVueComponent('email-verification-page', 'EmailVerificationPage', ['verified' => $verified, 'cancel' => $cancel]);
    }
}
