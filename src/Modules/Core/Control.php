<?php

namespace Foodsharing\Modules\Core;

use Foodsharing\Lib\Session;
use Foodsharing\Lib\View\Utils;
use Foodsharing\Modules\Unit\CurrentUserUnitsInterface;
use Foodsharing\Utility\FlashMessageHelper;
use Foodsharing\Utility\PageHelper;
use Foodsharing\Utility\RouteHelper;
use Symfony\Contracts\Service\Attribute\Required;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

abstract class Control
{
    protected PageHelper $pageHelper;
    protected Session $session;
    protected Utils $v_utils;
    private Environment $twig;
    protected FlashMessageHelper $flashMessageHelper;
    protected RouteHelper $routeHelper;
    protected TranslatorInterface $translator;
    protected CurrentUserUnitsInterface $currentUserUnits;

    public function __construct()
    {
        global $container;
        $this->session = $container->get(Session::class);
        $this->currentUserUnits = $container->get(CurrentUserUnitsInterface::class);
        $this->v_utils = $container->get(Utils::class);
        $this->pageHelper = $container->get(PageHelper::class);
        $this->routeHelper = $container->get(RouteHelper::class);
        $this->flashMessageHelper = $container->get(FlashMessageHelper::class);
        $this->translator = $container->get('translator');
    }

    #[Required]
    public function setTwig(Environment $twig): void
    {
        $this->twig = $twig;
    }

    protected function renderContent(string $template, array $data = []): string
    {
        $global = $this->pageHelper->generateAndGetGlobalViewData();
        $viewData = array_merge($global, $data);

        return $this->twig->render($template, $viewData);
    }
}
