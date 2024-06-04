<?php

namespace Foodsharing\Modules\Settings;

use Foodsharing\Lib\Session;
use Foodsharing\Lib\View\Utils;
use Foodsharing\Modules\Core\DBConstants\Foodsaver\Role;
use Foodsharing\Modules\Core\DBConstants\Map\MapConstants;
use Foodsharing\Modules\Core\DBConstants\Unit\UnitType;
use Foodsharing\Modules\Core\View;
use Foodsharing\Modules\Region\RegionGateway;
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

class SettingsView extends View
{
    private readonly RegionGateway $regionGateway;

    public function __construct(
        Environment $twig,
        Session $session,
        Utils $viewUtils,
        RegionGateway $regionGateway,
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
        $this->regionGateway = $regionGateway;

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
            $currentUserUnitsInterface
        );
    }

    public function sleepMode($sleep): string
    {
        return $this->vueComponent('sleeping-mode', 'SleepingMode', [
            'sleepStatus' => $sleep['sleep_status'],
            'sleepFrom' => $sleep['sleep_from'],
            'sleepUntil' => $sleep['sleep_until'],
            'sleepMessage' => $sleep['sleep_msg']
        ]);
    }

    public function settingsInfo()
    {
        return $this->vueComponent('notifications', 'Notifications');
    }

    public function changemail3($email)
    {
        return $this->v_utils->v_info($this->translator->trans('settings.changemail.question') . ' <strong>' . $email . '</strong> ?');
    }

    public function passport(): string
    {
        return $this->vueComponent('passport', 'Passport', [
            'userId' => $this->session->id(),
        ]);
    }

    public function settingsCalendar(): string
    {
        return $this->vueComponent('calendar', 'Calendar', [
            'baseUrlWebcal' => WEBCAL_URL . '/api/calendar/',
            'baseUrlHttp' => BASE_URL . '/api/calendar/'
        ]);
    }

    public function delete_account(int $fsId): string
    {
        return $this->vueComponent('delete-account', 'DeleteAccount', [
            'userId' => $fsId
        ]);
    }

    public function foodsaver_form()
    {
        global $g_data;

        $regionPicker = '';
        $position = '';

        if ($this->session->mayRole(Role::ORGA)) {
            $bezirk = ['id' => 0, 'name' => false];
            if ($b = $this->regionGateway->getRegion($this->currentUserUnits->getCurrentRegionId())) {
                $bezirk['id'] = $b['id'];
                $bezirk['name'] = $b['name'];
            }

            $regionPicker .= $this->vueComponent('region-tree-vform', 'RegionTreeVForm', [
                'title' => $this->translator->trans('terminology.homeRegion'),
                'inputName' => 'bezirk_id',
                'value' => $bezirk,
                'selectableRegionTypes' => [UnitType::CITY, UnitType::DISTRICT, UnitType::REGION, UnitType::WORKING_GROUP, UnitType::PART_OF_TOWN],
            ]);
            $position = $this->v_utils->v_form_text('position');
        }

        $g_data['ort'] = $g_data['stadt'];

        $addressPicker = $this->vueComponent('settings-address-search', 'LeafletLocationSearchVForm', [
            'zoom' => 17,
            'coordinates' => ['lat' => $g_data['lat'] ?? MapConstants::CENTER_GERMANY_LAT, 'lon' => $g_data['lon'] ?? MapConstants::CENTER_GERMANY_LON],
            'street' => $g_data['anschrift'],
            'postalCode' => $g_data['plz'],
            'city' => $g_data['ort'],
            'additionalInfoText' => $this->translator->trans('addresspicker.infobox_profile'),
        ]);

        return $this->v_utils->v_quickform($this->translator->trans('settings.header'), [
            $this->vueComponent('name-input', 'NameInput', [
                'name' => $this->dataHelper->getValue('name'),
                'lastName' => $this->dataHelper->getValue('nachname'),
                'regionId' => $this->dataHelper->getValue('bezirk_id'),
            ]),
            $this->v_utils->v_form_date('geb_datum', ['required' => true, 'yearRangeFrom' => (int)date('Y') - 120, 'yearRangeTo' => (int)date('Y') - 8]),
            $this->v_utils->v_form_text('handy', ['placeholder' => $this->translator->trans('register.phone_example')]),
            $this->v_utils->v_form_text('telefon', ['placeholder' => $this->translator->trans('register.landline_example')]),
            $regionPicker,
            $addressPicker,
            $position,
            $this->v_utils->v_form_textarea('about_me_intern', [
                'desc' => $this->translator->trans('foodsaver.about_me_intern'),
            ]),
            $this->v_utils->v_form_textarea('about_me_public', [
                'desc' => $this->translator->trans('foodsaver.about_me_public'),
            ]),
            $this->v_utils->v_form_select('no_automatic_delete', [
                'values' => [
                    ['id' => 0, 'name' => $this->translator->trans('automatic_delete')],
                    ['id' => 1, 'name' => $this->translator->trans('automatic_not_delete')],
                ]
            ]),
        ], ['submit' => $this->translator->trans('button.save')]);
    }

    public function picture_box($photo): string
    {
        $p_cnt = $this->v_utils->v_info($this->translator->trans('settings.photo.info', [
            '{link_photo}' => 'https://wiki.foodsharing.de/Foto_-_Leitfaden_f%C3%BCr_ein_repr%C3%A4sentatives_Foto',
            '{link_id}' => 'https://wiki.foodsharing.de/Ausweis',
            '{link_fs}' => 'https://wiki.foodsharing.de/Foodsaver',
        ]));

        // find previous picture
        $initialValue = 'img/portrait.png';
        if (!empty($photo)) {
            if (str_starts_with((string)$photo, '/api/uploads/')) {
                // path for pictures uploaded with the new API
                $initialValue = $photo . '?w=200&h=257';
            } elseif (file_exists('images/thumb_crop_' . $photo)) {
                // backward compatible path for old pictures
                $initialValue = 'images/thumb_crop_' . $photo;
            }
        }

        // create picture upload component
        $p_cnt .= $this->vueComponent('image-upload', 'profile-picture', [
            'initialValue' => $initialValue,
            'imgHeight' => 400,
            'imgWidth' => 400,
        ]);

        return $this->v_utils->v_field($p_cnt, $this->translator->trans('settings.photo.title'));
    }
}
