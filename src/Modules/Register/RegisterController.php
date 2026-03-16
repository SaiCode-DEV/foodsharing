<?php

namespace Foodsharing\Modules\Register;

use Foodsharing\Lib\FoodsharingController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;

class RegisterController extends FoodsharingController
{
    public function __construct(
        private readonly RegisterGateway $registerGateway,
    ) {
        parent::__construct();
    }

    /**
     * First part of the registration process: input form for the email address.
     */
    #[Route(path: '/register', name: 'register')]
    public function index(#[MapQueryParameter] bool $tokenExpired = false): Response
    {
        if ($this->session->mayRole()) {
            $this->flashMessageHelper->info($this->translator->trans('register.account-exists'));

            return $this->redirectToRoute('dashboard');
        } else {
            $this->pageHelper->addBread($this->translator->trans('register.title'));
            $this->pageHelper->addTitle($this->translator->trans('register.title'));

            $this->pageHelper->addContent($this->prepareVueComponent('register-email', 'RegisterEmail', [
                'showTokenExpiredMessage' => $tokenExpired,
            ]));
        }

        return $this->renderGlobal();
    }

    /**
     * Second part of the registration process. This requires a valid token that was generated in the first part.
     */
    #[Route(path: '/register-continue', name: 'register-continue')]
    public function registerContinue(#[MapQueryParameter] string $token): Response
    {
        if ($this->session->mayRole()) {
            $this->flashMessageHelper->info($this->translator->trans('register.account-exists'));

            return $this->redirectToRoute('dashboard');
        }

        $email = $this->registerGateway->getEmailForToken($token);
        if (is_null($email)) {
            return $this->redirectToRoute('register', [
                'tokenExpired' => true,
            ]);
        }

        $this->pageHelper->addBread($this->translator->trans('register.title'));
        $this->pageHelper->addTitle($this->translator->trans('register.title'));
        $this->pageHelper->addContent($this->prepareVueComponent('register-form', 'RegisterForm', [
            'token' => $token,
        ]));

        return $this->renderGlobal();
    }
}
