<?php

namespace Foodsharing\Modules\Basket;

use Foodsharing\Lib\Session;
use Foodsharing\Lib\View\Utils;
use Foodsharing\Lib\View\vPage;
use Foodsharing\Modules\Basket\DTO\Basket;
use Foodsharing\Modules\Core\DTO\GeoLocation;
use Foodsharing\Modules\Core\View;
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

class BasketView extends View
{
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
        CurrentUserUnitsInterface $currentUserUnitsInterface
    ) {
        parent::__construct(
            $twig,
            $session,
            $viewUtils,
            $dataHelper,
            $identificationHelper,
            $imageService,
            $numberHelper,
            $pageHelper,
            $routeHelper,
            $sanitizerService,
            $timeHelper,
            $translationHelper,
            $translator,
            $currentUserUnitsInterface,
        );
    }

    public function find(array $baskets, GeoLocation $location, int $zoom): void
    {
        $map = $this->vueComponent('baskets-location-map', 'BasketsLocationMap', [
            'center' => $location,
            'zoom' => $zoom,
        ]);
        $page = new vPage($this->translator->trans('terminology.baskets'), $map);

        if ($baskets) {
            $label = $this->translator->trans('basket.nearby-short');
            $page->addSectionRight($this->vueComponent('nearby-baskets-list', 'NearbyBasketsList', [
                'baskets' => $baskets,
            ]), $label);
        }

        $page->render();
    }

    public function basketTaken(Basket $basket): void
    {
        $label = $this->translator->trans('terminology.basket') . ' #' . $basket->id;
        $page = new vPage($label,
            '<div>
				<p>' . $this->translator->trans('basket.taken') . '</p>
			</div>');
        $page->render();
    }
}
