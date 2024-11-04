<?php

namespace Foodsharing\Modules\Core;

use Foodsharing\Lib\Session;
use Foodsharing\Lib\View\Utils;
use Foodsharing\Modules\Unit\CurrentUserUnitsInterface;
use Foodsharing\Utility\DataHelper;
use Foodsharing\Utility\IdentificationHelper;
use Foodsharing\Utility\ImageHelper;
use Foodsharing\Utility\NumberHelper;
use Foodsharing\Utility\PageHelper;
use Foodsharing\Utility\RouteHelper;
use Foodsharing\Utility\Sanitizer;
use Foodsharing\Utility\TimeHelper;
use Foodsharing\Utility\TranslationHelper;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

class View
{
    protected Session $session;
    protected Utils $v_utils;

    public Environment $twig;

    protected DataHelper $dataHelper;
    protected IdentificationHelper $identificationHelper;
    protected ImageHelper $imageService;
    protected NumberHelper $numberHelper;
    protected PageHelper $pageHelper;
    protected RouteHelper $routeHelper;
    protected Sanitizer $sanitizerService;
    protected TimeHelper $timeHelper;
    protected TranslationHelper $translationHelper;
    protected TranslatorInterface $translator;

    public function __construct(
        Environment $twig,
        Session $session,
        Utils $viewUtils,
        DataHelper $dataHelper,
        IdentificationHelper $identificationHelper,
        ImageHelper $imageService,
        NumberHelper $numberHelper,
        PageHelper $pageHelper,
        RouteHelper $routeHelper,
        Sanitizer $sanitizerService,
        TimeHelper $timeHelper,
        TranslationHelper $translationHelper,
        TranslatorInterface $translator,
        protected readonly CurrentUserUnitsInterface $currentUserUnits,
    ) {
        $this->twig = $twig;
        $this->session = $session;
        $this->v_utils = $viewUtils;
        $this->dataHelper = $dataHelper;
        $this->identificationHelper = $identificationHelper;
        $this->imageService = $imageService;
        $this->numberHelper = $numberHelper;
        $this->pageHelper = $pageHelper;
        $this->routeHelper = $routeHelper;
        $this->sanitizerService = $sanitizerService;
        $this->timeHelper = $timeHelper;
        $this->translationHelper = $translationHelper;
        $this->translator = $translator;
    }

    public function topbar(string $title, string $subtitle = '', string $icon = ''): string
    {
        if ($icon != '') {
            $icon = '<div class="img">' . $icon . '</div>';
        }

        if ($subtitle != '') {
            $subtitle = '<p>' . $subtitle . '</p>';
        }

        return '
		<div class="content-top corner-all">
			' . $icon . '
			<h3>' . $title . '</h3>
			' . $subtitle . '
			<div class="clear"></div>
		</div>';
    }

    public function menu(array $items, array $option = []): string
    {
        $title = false;
        if (isset($option['title'])) {
            $title = $option['title'];
        }

        $active = false;
        if (isset($option['active'])) {
            $active = $option['active'];
        }

        $id = $this->identificationHelper->id('vmenu');

        $out = '';

        foreach ($items as $item) {
            if (!isset($item['href'])) {
                $item['href'] = '#';
            }

            $click = '';
            if (isset($item['click'])) {
                $click = ' onclick="' . $item['click'] . '"';
            }
            $class = '';
            if ($active !== false && str_contains((string)$item['href'], '=' . $active)) {
                $class = 'active ';
            }

            $out .= '<li>
					<a class="' . $class . 'ui-corner-all" href="' . $item['href'] . '"' . $click . '>
						<span>' . $item['name'] . '</span>
					</a>
				</li>';
        }

        if (!$title) {
            return '
		<div class="ui-widget ui-widget-content ui-corner-all ui-padding margin-bottom">
			<ul class="linklist">
				' . $out . '
			</ul>
		</div>';
        }

        return '
		<h3 class="head ui-widget-header ui-corner-top">' . $title . '</h3>
		<div class="ui-widget ui-widget-content ui-corner-bottom ui-padding margin-bottom">
			<ul class="linklist">
				' . $out . '
			</ul>
		</div>';
    }

    public function vueComponent(string $id, string $component, array $props = [], array $data = []): string
    {
        return $this->twig->render('partials/vue-wrapper.twig', [
            'id' => $id,
            'component' => $component,
            'props' => $props,
            'initialData' => $data,
        ]);
    }
}
