<?php

namespace Foodsharing\Modules\Register;

use Foodsharing\Lib\FoodsharingController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class RegisterController extends FoodsharingController
{
    public function __construct()
    {
        parent::__construct();
    }

    #[Route(path: '/register', name: 'register')]
    public function index(): Response
    {
        if ($this->session->mayRole()) {
            $this->flashMessageHelper->info($this->translator->trans('register.account-exists'));
            $this->routeHelper->goAndExit('/?page=dashboard');
        } else {
            $this->pageHelper->addBread($this->translator->trans('register.title'));
            $this->pageHelper->addTitle($this->translator->trans('register.title'));

            $this->pageHelper->addContent($this->prepareVueComponent('register-form', 'RegisterForm'));
        }

        return $this->renderGlobal();
    }
}
