<?php

namespace Foodsharing\Modules\Basket;

use Foodsharing\Lib\Session;
use Foodsharing\Lib\View\Utils;
use Foodsharing\Lib\View\vPage;
use Foodsharing\Modules\Basket\DTO\Basket;
use Foodsharing\Modules\Core\DBConstants\Map\MapConstants;
use Foodsharing\Modules\Core\DTO\GeoLocation;
use Foodsharing\Modules\Core\View;
use Foodsharing\Modules\Unit\CurrentUserUnitsInterface;
use Foodsharing\Permissions\BasketPermissions;
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
    private readonly BasketPermissions $basketPermissions;

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
        BasketPermissions $basketPermissions,
        CurrentUserUnitsInterface $currentUserUnitsInterface
    ) {
        $this->basketPermissions = $basketPermissions;
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

    public function basket(Basket $basket, $requests): void
    {
        if (!$this->session->mayRole()) {
            $this->pageHelper->addContent($this->v_utils->v_info(
                $this->translator->trans('basket.login'),
                $this->translator->trans('notice')
            ), CNT_MAIN);
        }

        $vue = $this->vueComponent('vue-basket-container', 'basket-container', [
            'basket' => $basket,
        ]);

        $this->pageHelper->addContent($vue);

        $label = $this->translator->trans('terminology.basket') . ' #' . $basket->id;

        if ($this->session->mayRole()) {
            $label = $this->translator->trans('basket.provider');
            $this->pageHelper->addContent('<div class="page-container page render"><h3>' . $label . '</h3>
                ' . $this->userBox($basket, $requests) . '
            </div>', CNT_RIGHT);

            if ($basket->creator->id == $this->session->id() && $requests) {
                $label = $this->translator->trans('basket.requests', ['{count}' => count($requests)]);
                $this->pageHelper->addContent('<div class="page-container page render"><h3>' . $label . '</h3>
                    ' . $this->requests($requests) . '
                </div>', CNT_RIGHT);
            }

            if ($basket->location->lat != 0 || $basket->location->lon != 0) {
                $map = $this->vueComponent('basket-location-map', 'BasketLocationMap', [
                    'zoom' => MapConstants::ZOOM_CITY,
                    'coordinates' => $basket->location,
                ]);

                $label = $this->translator->trans('basket.where');
                $this->pageHelper->addContent('<div class="page-container page render"><h3>' . $label . '</h3>
                    ' . $map . '
                </div>', CNT_RIGHT);
            }
        }
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

    public function requests(array $requests): string
    {
        $out = '<ul class="linklist conversation-list">';

        foreach ($requests as $r) {
            $img = $this->imageService->img($r['fs_photo']);
            $out .= '<li><a onclick="chat(' . (int)$r['fs_id'] . '); return false;" href="#">'
                . '<span class="pics"><img width="50" alt="avatar" src="' . $img . '"></span>'
                . '<span class="names">' . $r['fs_name'] . '</span>'
                . '<span class="msg"></span>'
                . '<span class="time">' . $this->timeHelper->niceDate($r['time_ts']) . '</span>'
                . '<span class="clear"></span>
			</a></li>';
        }

        return $out . '</ul>';
    }

    private function userBox(Basket $basket, array $requests): string
    {
        $request = '';

        if ($this->basketPermissions->mayRequest($basket->creator->id)) {
            $hasRequested = $requests ? true : false;

            $allowContactByMessage = in_array(1, $basket->contactTypes);
            $allowContactByPhone = in_array(2, $basket->contactTypes);

            $request = $this->vueComponent('vue-BasketRequestForm', 'request-form', [
                'basketId' => $basket->id,
                'basketCreatorId' => $basket->creator->id,
                'initialHasRequested' => $hasRequested,
                'initialRequestCount' => $basket->requestCount,
                'mobileNumber' => ($allowContactByPhone && !empty($basket->mobile)) ? $basket->mobile : null,
                'landlineNumber' => ($allowContactByPhone && !empty($basket->telephone)) ? $basket->telephone : null,
                'allowRequestByMessage' => $allowContactByMessage
            ]);
        }

        if ($this->basketPermissions->mayDelete($basket)) {
            $request .= $this->vueComponent('vue-basket-edit-form', 'edit-form', [
                'basket' => $basket,
                'mayEdit' => $this->basketPermissions->mayEdit($basket->creator->id),
            ]);
        }

        $creator = $this->vueComponent('basket-creator', 'AvatarList', [
            'profiles' => [$basket->creator],
            'maxVisibleAvatars' => 1,
        ]);

        return $creator . $request;
    }
}
