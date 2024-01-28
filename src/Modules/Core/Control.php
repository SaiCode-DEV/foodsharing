<?php

namespace Foodsharing\Modules\Core;

use Foodsharing\Lib\Db\Mem;
use Foodsharing\Lib\Session;
use Foodsharing\Lib\View\Utils;
use Foodsharing\Utility\EmailHelper;
use Foodsharing\Utility\FlashMessageHelper;
use Foodsharing\Utility\PageHelper;
use Foodsharing\Utility\RouteHelper;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Service\Attribute\Required;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

abstract class Control
{
    protected $view;
    private false|string $sub = false;

    protected PageHelper $pageHelper;
    protected Mem $mem;
    protected Session $session;
    protected Utils $v_utils;
    private Environment $twig;
    protected Request $request;
    protected EmailHelper $emailHelper;
    protected FlashMessageHelper $flashMessageHelper;
    protected RouteHelper $routeHelper;
    protected TranslatorInterface $translator;

    public function __construct()
    {
        global $container;
        $this->mem = $container->get(Mem::class);
        $this->session = $container->get(Session::class);
        $this->v_utils = $container->get(Utils::class);
        $this->pageHelper = $container->get(PageHelper::class);
        $this->emailHelper = $container->get(EmailHelper::class);
        $this->routeHelper = $container->get(RouteHelper::class);
        $this->flashMessageHelper = $container->get(FlashMessageHelper::class);
        $this->translator = $container->get('translator');
        if (isset($_GET['sub'])) {
            $sub = $_GET['sub'];

            if (method_exists($this, $sub)) {
                $this->sub = $sub;
            }
        }
    }

    #[Required]
    public function setTwig(Environment $twig): void
    {
        $this->twig = $twig;
    }

    public function setRequest(Request $req): void
    {
        $this->request = $req;
    }

    protected function render(string $template, array $data = []): string
    {
        $global = $this->pageHelper->generateAndGetGlobalViewData();
        $viewData = array_merge($global, $data);

        return $this->twig->render($template, $viewData);
    }

    public function getSub()
    {
        return $this->sub;
    }

    public function submitted(): bool
    {
        return !empty($_POST);
    }
}
