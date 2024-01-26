<?php

namespace Foodsharing\Modules\Store;

use Foodsharing\Lib\Session;
use Foodsharing\Lib\View\Utils;
use Foodsharing\Modules\Core\DBConstants\Store\PublicTimes;
use Foodsharing\Modules\Core\DBConstants\Store\StoreSettings;
use Foodsharing\Modules\Core\View;
use Foodsharing\Modules\Store\DTO\CommonStoreMetadata;
use Foodsharing\Modules\Store\DTO\Store;
use Foodsharing\Utility\DataHelper;
use Foodsharing\Utility\IdentificationHelper;
use Foodsharing\Utility\ImageHelper;
use Foodsharing\Utility\NumberHelper;
use Foodsharing\Utility\PageHelper;
use Foodsharing\Utility\RouteHelper;
use Foodsharing\Utility\Sanitizer;
use Foodsharing\Utility\TimeHelper;
use Foodsharing\Utility\TranslationHelper;
use Foodsharing\Utility\WeightHelper;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

class StoreView extends View
{
    private $weightHelper;

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
        WeightHelper $weightHelper,
        TranslatorInterface $translator,
    ) {
        $this->weightHelper = $weightHelper;
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
        );
    }

    public function betrieb_form(CommonStoreMetadata $common, $region = false, $page = '')
    {
        global $g_data;

        $regionPicker = $this->v_utils->v_regionPicker($region ?: [], $this->translator->trans('terminology.region'));

        if (isset($g_data['stadt'])) {
            $g_data['ort'] = $g_data['stadt'];
        }
        if (isset($g_data['str'])) {
            $g_data['anschrift'] = $g_data['str'];
        }

        $this->pageHelper->addJs('$("textarea").css("height","70px");$("textarea").autosize();');

        $latLonOptions = [];

        foreach (['anschrift', 'plz', 'ort', 'lat', 'lon'] as $i) {
            if (isset($g_data[$i])) {
                $latLonOptions[$i] = $g_data[$i];
            }
        }
        if (isset($g_data['lat'], $g_data['lon'])) {
            $latLonOptions['location'] = ['lat' => $g_data['lat'], 'lon' => $g_data['lon']];
        } else {
            $latLonOptions['location'] = ['lat' => 0, 'lon' => 0];
        }

        $editExisting = !$this->identificationHelper->getAction('new');

        $categoryValues = array_map(fn ($row) => (array)$row, $common->categories);
        $storeChainsValues = $common->storeChains ? array_map(fn ($row) => (array)$row, $common->storeChains) : [];
        $cooperationStatus = array_map(fn ($row) => (array)$row, $common->status);
        $groceriesValues = array_map(fn ($row) => (array)$row, $common->groceries);
        $weightValues = array_map(fn ($row) => (array)$row, $common->weight);
        $publicTimesWithoutNotSelected = array_map(fn ($row) => (array)$row, $common->publicTimes);
        $publicTimeNotSelected = ['id' => 0, 'name' => $this->translator->trans('store.nodeclaration')];
        $publicTimesWithNoSelection = array_merge([$publicTimeNotSelected], $publicTimesWithoutNotSelected);

        $convinceStatusValues = array_map(fn ($row) => (array)$row, $common->convinceStatus);
        $prefetchTimeValues = [
            ['id' => 604800, 'name' => $this->translator->trans('store.prefetchone')],
            ['id' => 1_209_600, 'name' => $this->translator->trans('store.prefetchtwo')],
            ['id' => 1_814_400, 'name' => $this->translator->trans('store.prefetchthree')],
            ['id' => 2_419_200, 'name' => $this->translator->trans('store.prefetchfour')]
        ];

        $fieldset = array_merge($editExisting ? [] : [
            /* elements that are only present when creating */
            $this->v_utils->v_form_textarea('first_post', ['required' => true]),
        ], [
            /* elements that are always present */
            $this->v_utils->v_form_hidden('page', $page),
            $this->v_utils->v_form_text('name', ['required' => true]),
            $regionPicker,
            $this->latLonPicker('LatLng', $latLonOptions),
/*the next lines give the max number of chars for the info to be entered and a warning not to give too many details*/
            $this->v_utils->v_form_textarea('public_info', [
                'maxlength' => 180,
                'desc' => $this->translator->trans('store.leaveinfo') . '<br />' . $this->translator->trans('store.maxchar') . '<div>' . $this->v_utils->v_info('<strong> ' . $this->translator->trans('store.important') . '</strong>' . $this->translator->trans('store.nodetails') . '<br />' . $this->translator->trans('store.peoplecame')) . '</div>',
            ]),
        ], $editExisting ? [
            /* elements that are only present when editing */
            $this->v_utils->v_form_select('betrieb_kategorie_id', ['values' => $categoryValues]),
            $this->v_utils->v_form_select('kette_id', [
                'values' => $storeChainsValues,
                'desc' => $this->translator->trans('store.nochains'),
            ]),
            $this->v_utils->v_form_select('betrieb_status_id', [
                'values' => $cooperationStatus,
                'desc' => $this->v_utils->v_info($this->translator->trans('store_status_impact_explanation')),
            ]),

            $this->v_utils->v_form_textarea('besonderheiten', [
                'desc' => $this->v_utils->v_info($this->translator->trans('formatting.md'), '', '<i class="fab fa-markdown fa-2x d-inline align-middle text-muted"></i>')
            ]),

            $this->v_utils->v_form_checkbox('lebensmittel', ['values' => $groceriesValues]),
            $this->v_utils->v_form_text('ansprechpartner'),
            $this->v_utils->v_form_text('telefon'),
            $this->v_utils->v_form_text('fax'),
            $this->v_utils->v_form_text('email'),
            $this->v_utils->v_form_date('begin'),
            $this->v_utils->v_form_select('public_time', ['values' => $publicTimesWithNoSelection]),
            $this->v_utils->v_form_select('prefetchtime', ['values' => $prefetchTimeValues]),
            $this->v_utils->v_form_select('use_region_pickup_rule', ['values' => [
                ['id' => StoreSettings::USE_PICKUP_RULE_YES, 'name' => $this->translator->trans('yes')],
                ['id' => StoreSettings::USE_PICKUP_RULE_NO, 'name' => $this->translator->trans('no')]
            ]]),
            $this->v_utils->v_form_select('abholmenge', ['values' => $weightValues]),
            $this->v_utils->v_form_select('ueberzeugungsarbeit', ['values' => $convinceStatusValues]),
            $this->v_utils->v_form_select('presse', ['values' => [
                ['id' => StoreSettings::PRESS_YES, 'name' => $this->translator->trans('yes')],
                ['id' => StoreSettings::PRESS_NO, 'name' => $this->translator->trans('no')]
            ]]),
            $this->v_utils->v_form_select('sticker', ['values' => [
                ['id' => 1, 'name' => $this->translator->trans('yes')],
                ['id' => 0, 'name' => $this->translator->trans('no')]
            ]]),
        ] : []);

        return $this->v_utils->v_quickform($this->translator->trans('storeview.store'), $fieldset);
    }

    public function bubble(array $store): string
    {
        $managers = '<ul class="linklist">';
        foreach ($store['foodsaver'] as $fs) {
            if ($fs['verantwortlich'] == 1) {
                $managers .= '<li>' .
                    '<a style="background-color: var(--fs-color-transparent);" href="/profile/' . intval($fs['id']) . '">'
                    . $this->imageService->avatar($fs, 50) .
                    '</a></li>';
            }
        }
        $managers .= '</ul>';

        $count_info = '<div>' . $this->translator->trans('storeview.teamInfo', [
            '{active}' => count($store['foodsaver']),
            '{jumper}' => count($store['springer']),
        ]) . '</div>';

        $pickup_count = intval($store['pickup_count']);
        if ($pickup_count > 0) {
            $count_info .= '<div>' . $this->translator->trans('storeview.pickupCount', [
                '{pickupCount}' => $this->translator->trans('storeview.counter', [
                    '{suffix}' => 'x',
                    '{count}' => $pickup_count,
                ]),
            ]) . '</div>';

            $pickupWeight = $this->translator->trans('storeview.counter', [
                '{suffix}' => 'kg',
                '{count}' => round(floatval(
                    $pickup_count * $this->weightHelper->mapIdToKilos($store['abholmenge'])
                ), 2),
            ]);
            $count_info .= '<div>' . $this->translator->trans('storeview.pickupWeight', [
                '{pickupWeight}' => $pickupWeight,
            ]) . '</div>';
        }

        $when = strtotime((string)$store['begin']);
        if ($when > 0) {
            $startTime = $this->translator->trans('month.' . intval(date('m', $when))) . ' ' . date('Y', $when);
            $count_info .= '<div>' . $this->translator->trans('storeview.cooperation', [
                '{startTime}' => $startTime,
            ]) . '</div>';
        }

        $fetchTime = intval($store['public_time']);
        if ($fetchTime != 0) {
            $meaning = match (PublicTimes::from($fetchTime)) {
                PublicTimes::NOT_SET => '',
                PublicTimes::IN_THE_MORNING => $this->translator->trans('storeview.public_time_in_the_morning'),
                PublicTimes::AT_NOON_IN_THE_AFTERNOON => $this->translator->trans('storeview.public_time_at_noon_or_afternoon'),
                PublicTimes::IN_THE_EVENING => $this->translator->trans('storeview.public_time_in_the_evening'),
                PublicTimes::AT_NIGHT => $this->translator->trans('storeview.public_time_at_night')
            };

            if (!empty($meaning)) {
                $count_info .= '<div>' . $this->translator->trans('storeview.public_time', [
                    '{freq}' => $meaning,
                ]) . '</div>';
            }
        }

        $publicInfo = '';
        if (!empty($store['public_info'])) {
            $publicInfo = $this->v_utils->v_input_wrapper(
                $this->translator->trans('storeview.info'),
                $store['public_info'],
                'bcntspecial'
            );
        }

        $status = $this->v_utils->v_getStatusAmpel($store['betrieb_status_id']);

        // Store status
        $bstatus = $this->translator->trans('storestatus.' . intval($store['betrieb_status_id'])) . '.';
        // Team status
        $tstatus = $this->translator->trans('storeedit.fetch.teamStatus' . intval($store['team_status']));

        $html = $this->v_utils->v_input_wrapper(
            $this->translator->trans('storeedit.store.status'),
            $status . '<span class="bstatus">' . $bstatus . '</span>' . $count_info
        ) . $this->v_utils->v_input_wrapper(
            $this->translator->trans('storeview.managers'), $managers, 'bcntverantwortlich'
        ) . $publicInfo . '<div class="ui-padding">'
        . $this->v_utils->v_info('<strong>' . $tstatus . '</strong>') . '</div>';

        return $html;
    }

    public function storeOwnList(): string
    {
        return $this->vueComponent('vue-store-own-list', 'StoreOwnList');
    }
}
