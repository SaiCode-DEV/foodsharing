<?php

namespace Foodsharing\Modules\Basket;

use Foodsharing\Lib\Session;
use Foodsharing\Lib\View\Utils;
use Foodsharing\Lib\View\vPage;
use Foodsharing\Modules\Core\DBConstants\Map\MapConstants;
use Foodsharing\Modules\Core\View;
use Foodsharing\Modules\Foodsaver\Profile;
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

class BasketView extends View
{
    private BasketPermissions $basketPermissions;

    public function __construct(
        \Twig\Environment $twig,
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
        BasketPermissions $basketPermissions
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
            $translator
        );
    }

    public function find(array $baskets, $location): void
    {
        $page = new vPage($this->translator->trans('terminology.baskets'), $this->findMap($location));

        if ($baskets) {
            $label = $this->translator->trans('basket.nearby-short');
            $page->addSectionRight($this->nearbyBaskets($baskets), $label);
        }

        $page->render();
    }

    private function findMap($location): string
    {
        if (is_array($location)) {
            $center = ['lat' => $location['lat'], 'lon' => $location['lon']];
            $zoom = MapConstants::ZOOM_CITY;
        } else {
            $center = ['lat' => MapConstants::CENTER_GERMANY_LAT, 'lon' => MapConstants::CENTER_GERMANY_LON];
            $zoom = MapConstants::ZOOM_COUNTRY;
        }

        return $this->vueComponent('baskets-location-map', 'BasketsLocationMap', [
            'center' => $center,
            'zoom' => $zoom,
        ]);
    }

    public function nearbyBaskets(array $baskets): string
    {
        $out = '
		<ul class="linklist" id="cbasketlist">';
        foreach ($baskets as $b) {
            $img = '/img/basket.png';
            if (!empty($b['picture'])) {
                if (str_starts_with($b['picture'], '/api')) {
                    $img = $b['picture'] . '?w=35&h=35';
                } else {
                    $img = '/images/basket/thumb-' . $b['picture'];
                }
            }

            $distance = $this->numberHelper->format_distance($b['distance']);

            $out .= '<li>
				<a class="ui-corner-all" onclick="ajreq(\'bubble\','
                . '{app: \'basket\''
                . ',id:' . (int)$b['id']
                . ',modal: 1'
                . '}); return false;" href="#">
					<span style="float: left; margin-right: 7px;">
						<img width="35px" src="' . $img . '" class="ui-corner-all">
					</span>
					<span style="height: 35px; overflow: hidden; font-size: 11px; line-height: 16px;">
						<strong style="float: right; margin: 0 0 0 3px;">(' . $distance . ')</strong>'
                        . $this->sanitizerService->tt($b['description'], 50) . '
					</span>
					<span class="clear"></span>
				</a>
			</li>';
        }

        return $out . '
		</ul>
		<div style="text-align: center;">
			<a class="button" href="/karte?load=baskets">' . $this->translator->trans('basket.all_map') . '</a>
		</div>';
    }

    public function basket(array $basket, $requests): void
    {
        $label = $this->translator->trans('terminology.basket') . ' #' . $basket['id'];
        $page = new vPage($label,
            '<div class="fbasket-wrap">
				<div class="fbasket-pic">
					' . $this->pageImg($basket['picture'] ?? '') . '
				</div>
				<div class="fbasket-desc">
					<p>' . nl2br($basket['description']) . '</p>
				</div>
			</div>');

        $page->setSubTitle($this->getSubtitle($basket));

        if ($this->session->mayRole()) {
            $page->addSection($this->v_utils->v_info($this->translator->trans('basket.howto')));

            $label = $this->translator->trans('basket.provider');
            $page->addSectionRight($this->userBox($basket, $requests), $label);

            if ($basket['fs_id'] == $this->session->id() && $requests) {
                $label = $this->translator->trans('basket.requests', ['{count}' => count($requests)]);
                $page->addSectionRight($this->requests($requests), $label);
            }

            if ($basket['lat'] != 0 || $basket['lon'] != 0) {
                $map = $this->vueComponent('basket-location-map', 'BasketLocationMap', [
                    'zoom' => MapConstants::ZOOM_CITY,
                    'coordinates' => ['lat' => $basket['lat'], 'lon' => $basket['lon']],
                ]);

                $page->addSectionRight($map, $this->translator->trans('basket.where'));
            }
        } else {
            $page->addSection(
                $this->v_utils->v_info(
                    $this->translator->trans('basket.login'),
                    $this->translator->trans('notice')
                ),
                false,
                ['wrapper' => false]
            );
        }

        $page->render();
    }

    public function basketTaken(array $basket): void
    {
        $label = $this->translator->trans('terminology.basket') . ' #' . $basket['id'];
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

    private function getSubtitle(array $basket): string
    {
        $created = $this->timeHelper->niceDate($basket['time_ts']);
        $expires = $this->timeHelper->niceDate($basket['until_ts']);

        $subtitle = '<p>' . $this->translator->trans('basket.created', ['{date}' => $created]) . '</p>';
        $subtitle .= '<p>' . $this->translator->trans('basket.expires', ['{date}' => $expires]) . '</p>';

        if ($basket['update_ts']) {
            $updated = $this->timeHelper->niceDate($basket['update_ts']);
            $subtitle .= '<p>' . $this->translator->trans('basket.updated', ['{date}' => $updated]) . '</p>';
        }

        return $subtitle;
    }

    private function userBox(array $basket, array $requests): string
    {
        $request = '';

        if ($this->basketPermissions->mayRequest($basket['fs_id'])) {
            $hasRequested = $requests ? true : false;

            if (!empty($basket['contact_type'])) {
                $contact_type = explode(':', $basket['contact_type']);
            } else {
                $contact_type = [];
            }
            $allowContactByMessage = in_array(1, $contact_type);
            $allowContactByPhone = in_array(2, $contact_type);

            $request = $this->vueComponent('vue-BasketRequestForm', 'request-form', [
                'basketId' => $basket['id'],
                'basketCreatorId' => $basket['foodsaver_id'],
                'initialHasRequested' => $hasRequested,
                'initialRequestCount' => $basket['request_count'],
                'mobileNumber' => ($allowContactByPhone && !empty($basket['handy'])) ? $basket['handy'] : null,
                'landlineNumber' => ($allowContactByPhone && !empty($basket['tel'])) ? $basket['tel'] : null,
                'allowRequestByMessage' => $allowContactByMessage
            ]);
        }

        if ($this->basketPermissions->mayDelete($basket)) {
            $request .= $this->vueComponent('vue-basket-edit-form', 'edit-form', [
                'basket' => $basket,
                'mayEdit' => $this->basketPermissions->mayEdit($basket['fs_id']),
            ]);
        }

        $basketUser = new Profile($basket['fs_id'], $basket['fs_name'], $basket['fs_photo'], $basket['sleep_status']);
        $creator = $this->vueComponent('basket-creator', 'AvatarList', [
            'profiles' => [$basketUser],
            'maxVisibleAvatars' => 1,
        ]);

        return $creator . $request;
    }

    private function pageImg(string $img): string
    {
        $imgUrl = '/img/foodloob.gif';
        if (!empty($img)) {
            if (str_starts_with($img, '/api')) {
                $imgUrl = $img;
            } else {
                $imgUrl = '/images/basket/medium-' . $img;
            }
        }

        return '<img class="basket-img" src="' . $imgUrl . '" />';
    }

    public function fsBubble(array $basket): string
    {
        $img = '';
        if (!empty($basket['picture'])) {
            $img = '<div style="width: 100%; max-height: 200px; overflow: hidden;">
				<img src="http://media.myfoodsharing.org/de/items/200/' . $basket['picture'] . '" />
			</div>';
        }

        return $img . $this->v_utils->v_input_wrapper(
            $this->translator->trans('basket.description'),
            nl2br($this->routeHelper->autolink($basket['description']))
        ) . '
		<div style="text-align: center;">
			<a class="fsbutton" href="' . BASE_URL . '/essenskoerbe/' . $basket['fsf_id'] . '" target="_blank">'
            . $this->translator->trans('basket.request-fs') .
            '</a>
		</div>';
    }
}
