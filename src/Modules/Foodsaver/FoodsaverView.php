<?php

namespace Foodsharing\Modules\Foodsaver;

use Foodsharing\Lib\Session;
use Foodsharing\Lib\View\Utils;
use Foodsharing\Modules\Core\DBConstants\Foodsaver\Role;
use Foodsharing\Modules\Core\DBConstants\Unit\UnitType;
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

class FoodsaverView extends View
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
        Sanitizer $sanitizer,
        TimeHelper $timeHelper,
        TranslationHelper $translationHelper,
        TranslatorInterface $translator,
        CurrentUserUnitsInterface $currentUserUnitsInterface,
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
            $sanitizer,
            $timeHelper,
            $translationHelper,
            $translator,
            $currentUserUnitsInterface
        );
    }

    public function foodsaver_form($title, $regionDetails): string
    {
        global $g_data;

        $orga = '';

        $position = '';

        if ($this->session->mayRole(Role::ORGA)) {
            $position = $this->v_utils->v_form_text('position');
            $options = [
                'values' => [
                    ['id' => 1, 'name' => $this->translator->trans('foodsaver.manage.orga')],
                ]
            ];

            if ($g_data['orgateam'] == 1) {
                $options['checkall'] = true;
            }

            $orga = $this->v_utils->v_form_checkbox('orgateam', $options);
            $orga .= $this->v_utils->v_form_select('rolle', [
                'values' => [
                    ['id' => 0, 'name' => $this->translator->trans('terminology.foodsharer.d')],
                    ['id' => 1, 'name' => $this->translator->trans('terminology.foodsaver.d')],
                    ['id' => 2, 'name' => $this->translator->trans('terminology.storemanager.d')],
                    ['id' => 3, 'name' => $this->translator->trans('terminology.ambassador.d')],
                    ['id' => 4, 'name' => $this->translator->trans('terminology.orga.d')],
                ]
            ]);
        }

        $this->pageHelper->addJs('
			$("#rolle").on("change", function () {
				if (this.value == 4) {
					$("#orgateam-wrapper input")[0].checked = true;
				} else {
					$("#orgateam-wrapper input")[0].checked = false;
				}
			});
		');

        $regionPicker = $this->vueComponent('region-tree-vform', 'RegionTreeVForm', [
            'title' => $this->translator->trans('terminology.homeRegion'),
            'inputName' => 'bezirk_id',
            'value' => $regionDetails,
            'selectableRegionTypes' => [UnitType::CITY, UnitType::DISTRICT, UnitType::REGION, UnitType::WORKING_GROUP, UnitType::PART_OF_TOWN],
        ]);

        $g_data['ort'] = $g_data['stadt'];
        foreach (['anschrift', 'plz', 'ort', 'lat', 'lon'] as $i) {
            $latLonOptions[$i] = $g_data[$i];
        }
        $latLonOptions['location'] = ['lat' => $g_data['lat'], 'lon' => $g_data['lon']];

        return $this->v_utils->v_quickform($title, [
            // TODO: When refactoring this form to vue, only show this alert when the name is actually changed.
            '<div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> ' . $this->translator->trans('profile.editNameInfo') . '</div>',
            $this->v_utils->v_form_text('name', ['required' => true]),
            $this->v_utils->v_form_text('nachname', ['required' => true]),
            $this->v_utils->v_form_date('geb_datum', [
                'required' => true,
                'yearRangeFrom' => ((int)date('Y') - 111),
                'yearRangeTo' => date('Y'),
            ]),
            $this->v_utils->v_form_select('geschlecht', ['values' => [
                ['id' => 2, 'name' => $this->translator->trans('gender.f')],
                ['id' => 1, 'name' => $this->translator->trans('gender.m')],
                ['id' => 3, 'name' => $this->translator->trans('gender.d')],
            ],
                ['required' => true]
            ]),
            $this->v_utils->v_form_text('handy', ['placeholder' => $this->translator->trans('register.phone_example')]),
            $this->v_utils->v_form_text('telefon', ['placeholder' => $this->translator->trans('register.landline_example')]),
            $regionPicker,
            $orga,
            $this->vueComponent('foodsaver-address-search', 'LeafletLocationSearchVForm', [
                'zoom' => 17,
                'coordinates' => $latLonOptions['location'],
                'street' => $latLonOptions['anschrift'] ?? null,
                'postalCode' => $latLonOptions['plz'] ?? null,
                'city' => $latLonOptions['ort'] ?? null,
            ]),
            $position,
            $this->v_utils->v_form_select('no_automatic_delete', [
                'values' => [
                    ['id' => 0, 'name' => $this->translator->trans('terminology.no')],
                    ['id' => 1, 'name' => $this->translator->trans('terminology.yes')],
                ]
            ]),
            $this->v_utils->v_form_text('email', ['required' => true, 'disabled' => true]),
        ], ['submit' => $this->translator->trans('button.save')]);
    }
}
