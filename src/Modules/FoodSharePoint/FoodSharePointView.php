<?php

namespace Foodsharing\Modules\FoodSharePoint;

use Foodsharing\Lib\Session;
use Foodsharing\Lib\View\Utils;
use Foodsharing\Modules\Core\DBConstants\Info\InfoType;
use Foodsharing\Modules\Core\View;
use Foodsharing\Modules\Unit\CurrentUserUnitsInterface;
use Foodsharing\Permissions\FoodSharePointPermissions;
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

class FoodSharePointView extends View
{
    private ?array $region = null;

    private readonly FoodSharePointPermissions $fspPermissions;

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
        FoodSharePointPermissions $fspPermissions,
        CurrentUserUnitsInterface $currentUserUnitsInterface,
    ) {
        $this->fspPermissions = $fspPermissions;
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

    public function setRegion(?array $region): void
    {
        $this->region = $region;
    }

    public function foodSharePointHead($foodSharePoint): string
    {
        return $this->twig->render('pages/FoodSharePoint/foodSharePointTop.html.twig', [
            'food_share_point' => $foodSharePoint,
        ]);
    }

    public function checkFoodSharePoint(array $foodSharePoint): string
    {
        $htmlEscapedName = htmlspecialchars((string)$foodSharePoint['name']);
        $content = '';
        if ($foodSharePoint['pic']) {
            $content .= $this->v_utils->v_input_wrapper($this->translator->trans('fsp.pic'),
                '<img src="' . $foodSharePoint['pic']['head'] . '" alt="' . $htmlEscapedName . '" />'
            );
        }

        $content .= $this->v_utils->v_input_wrapper($this->translator->trans('fsp.address'),
            $foodSharePoint['anschrift']
            . '<br />'
            . $foodSharePoint['plz'] . ' ' . $foodSharePoint['ort']
        );

        $content .= $this->v_utils->v_input_wrapper($this->translator->trans('fsp.description'),
            $this->sanitizerService->markdownToHtml($foodSharePoint['desc'])
        );

        $content .= $this->v_utils->v_input_wrapper($this->translator->trans('fsp.addedOn'),
            date('d.m.Y', $foodSharePoint['time_ts'])
        );

        $fsName = $foodSharePoint['fs_name'] . ' ' . $foodSharePoint['fs_nachname'];
        $content .= $this->v_utils->v_input_wrapper($this->translator->trans('fsp.addedBy'),
            '<a href="/profile/' . (int)$foodSharePoint['fs_id'] . '">' . $fsName . '</a>'
        );

        return $this->v_utils->v_field(
            $content,
            $this->translator->trans('fsp.acceptName', ['{name}' => $foodSharePoint['name']]),
            ['class' => 'ui-padding']
        );
    }

    public function address($foodSharePoint): string
    {
        return $this->vueComponent('fsp-address-field', 'AddressField', [
            'id' => $foodSharePoint['id'],
            'address' => $foodSharePoint['anschrift'],
            'zipCode' => $foodSharePoint['plz'],
            'city' => $foodSharePoint['ort'],
            'coordinates' => [
                'lat' => $foodSharePoint['lat'],
                'lon' => $foodSharePoint['lon']
            ]
        ]);
    }

    public function options(array $items): string
    {
        return $this->v_utils->v_menu($items, $this->translator->trans('options'));
    }

    public function followHidden(array $foodSharePoint, string $requestUri): string
    {
        $this->pageHelper->addJsFunc('
			function u_follow () {
				$("#follow-hidden").dialog("open");
			}
		');
        $this->pageHelper->addJs('
			$("#follow-hidden").dialog({
				modal: true,
				title: "' . $this->translator->trans('fsp.followName', [
                    '{name}' => $this->sanitizerService->jsSafe($foodSharePoint['name'], '"')
                ]) . '",
				autoOpen: false,
				width: 500,
				resizable: false,
				buttons: {
					"' . $this->translator->trans('button.save') . '": function () {
						goTo("' . $requestUri . '&follow=1&infotype=" + $("input[name=\'infotype\']:checked").val());
					}
				}
			});
		');

        return '<div id="follow-hidden">' . $this->v_utils->v_form_radio(
            'infotype',
            [
                'desc' => $this->translator->trans('fsp.info.descModal'),
                'values' => [
                    ['id' => InfoType::BELL, 'name' => $this->translator->trans('fsp.info.bell')],
                    ['id' => InfoType::EMAIL, 'name' => $this->translator->trans('fsp.info.mail')],
                ]
            ],
            1
        ) . '</div>';
    }

    public function follower(array $followers, array $managers): string
    {
        $out = '';

        if (!empty($managers)) {
            shuffle($managers);
            $out .= $this->v_utils->v_field(
                $this->vueComponent('fsp-managers', 'AvatarList', [
                    'profiles' => $managers,
                    'maxVisibleAvatars' => 5,
                ]),
                $this->translator->trans('fsp.managers')
            );
        }
        if (!empty($followers)) {
            shuffle($followers);
            $out .= $this->v_utils->v_field(
                $this->vueComponent('fsp-followers', 'AvatarList', [
                    'profiles' => $followers,
                    'maxVisibleAvatars' => 8,
                ]),
                $this->translator->trans('fsp.followers')
            );
        }

        return $out;
    }

    public function desc($foodSharePoint): string
    {
        return $this->v_utils->v_field(
            '<p>' . $this->sanitizerService->markdownToHtml($foodSharePoint['desc']) . '</p>',
            $this->translator->trans('fsp.description'),
            ['class' => 'ui-padding fsp-desc']
        );
    }

    public function listFoodSharePoints(array $regions): string
    {
        $content = '';
        $count = 0;
        foreach ($regions as $region) {
            $count += count($region['fairteiler']);
            $content .= $this->twig->render('partials/listFoodSharePointsForRegion.html.twig', [
                'region' => $region,
                'food_share_points' => $region['fairteiler'],
            ]);
        }

        $topbarHeader = $this->translator->trans('fsp.yours');
        $topbarText = $this->translator->trans('fsp.summary', ['{count}' => $count]);
        if ($this->region) {
            $regionName = $this->region['name'];
            $topbarHeader = $this->translator->trans('fsp.inRegion', ['{region}' => $regionName]);
            $topbarText = $this->translator->trans('fsp.summaryRegion', [
                '{count}' => $count,
                '{region}' => $regionName,
            ]);
        }

        $this->pageHelper->addContent($this->topbar(
            $topbarHeader,
            $topbarText,
            '<img src="/img/foodSharePointThumb.png" />'
        ), CNT_TOP);

        return $content;
    }

    public function foodSharePointOptions(int $regionId): string
    {
        $mayCreateFSP = $this->fspPermissions->mayAdd($regionId);

        $item = [
            'name' => $this->translator->trans($mayCreateFSP ? 'fsp.add' : 'fsp.suggest'),
            'href' => '/fairteiler?bid=' . $regionId . '&sub=add',
        ];

        return $this->v_utils->v_menu([$item], $this->translator->trans('options'));
    }
}
