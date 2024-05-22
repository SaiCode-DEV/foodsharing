<?php

namespace Foodsharing\Modules\Settings;

use Foodsharing\Modules\Core\Control;
use Foodsharing\Modules\Core\DBConstants\Foodsaver\Role;
use Foodsharing\Modules\Foodsaver\FoodsaverGateway;
use Foodsharing\Permissions\SettingsPermissions;
use Foodsharing\Utility\DataHelper;

class SettingsControl extends Control
{
    private readonly SettingsGateway $settingsGateway;
    private readonly FoodsaverGateway $foodsaverGateway;
    private readonly DataHelper $dataHelper;
    private readonly SettingsPermissions $settingsPermissions;

    public function __construct(
        SettingsView $view,
        SettingsGateway $settingsGateway,
        FoodsaverGateway $foodsaverGateway,
        DataHelper $dataHelper,
        SettingsPermissions $settingsPermissions,
    ) {
        $this->view = $view;
        $this->settingsGateway = $settingsGateway;
        $this->foodsaverGateway = $foodsaverGateway;
        $this->dataHelper = $dataHelper;
        $this->settingsPermissions = $settingsPermissions;

        parent::__construct();

        if (!$this->session->mayRole()) {
            $this->routeHelper->goLoginAndExit();
        }

        if (isset($_GET['newmail'])) {
            $this->handle_newmail();
        }

        if (!isset($_GET['sub'])) {
            $this->routeHelper->goAndExit('/?page=settings&sub=general');
        }

        $this->pageHelper->addTitle($this->translator->trans('settings.title'));
    }

    public function index()
    {
        $this->pageHelper->addBread($this->translator->trans('settings.title'), '/?page=settings');

        $menu = [
            ['name' => $this->translator->trans('settings.header'), 'href' => '/?page=settings&sub=general'],
            ['name' => $this->translator->trans('settings.notifications'), 'href' => '/?page=settings&sub=info'],
            ['name' => $this->translator->trans('settings.businesscard'), 'href' => '/?page=bcard'],
        ];

        if ($this->settingsPermissions->mayUseCalendarExport()) {
            $menu[] = ['name' => $this->translator->trans('settings.calendar.menu'), 'href' => '/?page=settings&sub=calendar'];
        }
        if ($this->session->mayRole(Role::FOODSAVER)) {
            $menu[] = ['name' => $this->translator->trans('settings.passport.menu'), 'href' => '/?page=settings&sub=passport'];
        }

        $this->pageHelper->addContent($this->view->menu($menu, [
            'title' => $this->translator->trans('settings.title'),
            'active' => $this->getSub(),
        ]), CNT_LEFT);

        $menu = [
            ['name' => $this->translator->trans('settings.sleep.title'), 'href' => '/?page=settings&sub=sleeping'],
            ['name' => $this->translator->trans('settings.email'), 'href' => '/?page=settings&sub=changeEmail'],
        ];

        $targetRole = $this->getNextTargetRole();
        if ($this->session->role()->value < $targetRole->value) {
            $menu[] = [
                'name' => $this->translator->trans('foodsaver.upgrade.' . $targetRole->name),
                'href' => '/?page=settings&sub=rise_role&role=' . $targetRole->value
            ];
        }

        $menu[] = [
            'name' => $this->translator->trans('foodsaver.delete_account'),
            'href' => '/?page=settings&sub=deleteaccount',
        ];

        $this->pageHelper->addContent($this->view->menu(
            $menu,
            ['title' => $this->translator->trans('settings.account'), 'active' => $this->getSub()]
        ), CNT_LEFT);
    }

    public function sleeping(): void
    {
        if ($sleep = $this->settingsGateway->getSleepData($this->session->id())) {
            $this->pageHelper->addContent($this->view->sleepMode($sleep));
        }
    }

    public function rise_role(): void
    {
        $targetRole = Role::tryFrom($_GET['role'] ?? null) ?? Role::AMBASSADOR;
        $maxTargetRole = $this->getNextTargetRole();
        if ($targetRole->value > $maxTargetRole->value) {
            $this->routeHelper->goAndExit('/?page=settings&sub=rise_role&role=' . $maxTargetRole->value);
        }
        $targetRole = Role::from(min($this->getNextTargetRole()->value, $targetRole->value));
        $this->pageHelper->addBread($this->translator->trans('foodsaver.upgrade.' . $targetRole->name));
        $this->pageHelper->addContent($this->view->vueComponent('vue-quiz-page', 'Quiz', [
            'quizId' => $targetRole->value,
        ]));
    }

    private function getNextTargetRole(): Role
    {
        $currentRole = $this->session->role();
        $targetRole = Role::AMBASSADOR;
        if ($currentRole->value < $targetRole->value - 1) {
            $targetRole = Role::from($currentRole->value + 1);
        }
        if (!$this->session->isVerified()) {
            $targetRole = Role::FOODSAVER;
        }

        return $targetRole;
    }

    public function deleteaccount(): void
    {
        $this->pageHelper->addBread($this->translator->trans('foodsaver.delete_account'));
        $this->pageHelper->addContent($this->view->delete_account($this->session->id()));
    }

    public function general()
    {
        $this->handle_edit();

        $data = $this->foodsaverGateway->getFoodsaver($this->session->id());

        $this->dataHelper->setEditData($data);

        $this->pageHelper->addContent($this->view->foodsaver_form());

        $this->pageHelper->addContent($this->picture_box(), CNT_RIGHT);
    }

    public function passport()
    {
        if ($this->session->mayRole(Role::FOODSAVER)) {
            $this->pageHelper->addBread($this->translator->trans('settings.passport.menu'));
            $this->pageHelper->addContent($this->view->passport());
        } else {
            $this->routeHelper->goAndExit('/?page=settings');
        }
    }

    public function calendar()
    {
        if ($this->settingsPermissions->mayUseCalendarExport()) {
            $this->pageHelper->addBread($this->translator->trans('settings.calendar.menu'));
            $this->pageHelper->addContent($this->view->settingsCalendar());
        } else {
            $this->routeHelper->goAndExit('/?page=settings');
        }
    }

    public function info()
    {
        $this->pageHelper->addBread($this->translator->trans('settings.notifications'));
        $this->pageHelper->addContent($this->view->settingsInfo());
    }

    public function changeEmail()
    {
        $this->pageHelper->addBread($this->translator->trans('settings.email'));
        $this->pageHelper->addContent($this->view->vueComponent('change-email-form', 'ChangeEmailForm'));
    }

    public function handle_edit()
    {
        if ($this->submitted()) {
            $data = $this->dataHelper->getPostData();
            $data['stadt'] = $data['ort'];
            $check = true;

            if (!empty($data['homepage'])) {
                if (!str_starts_with((string)$data['homepage'], 'http')) {
                    $data['homepage'] = 'http://' . $data['homepage'];
                }

                if (!$this->validUrl($data['homepage'])) {
                    $check = false;
                    $this->flashMessageHelper->error($this->translator->trans('foodsaver.url_error'));
                }
            }

            if ($check) {
                if ($oldFs = $this->foodsaverGateway->getFoodsaver($this->session->id())) {
                    $logChangedFields = ['stadt', 'plz', 'anschrift', 'telefon', 'handy', 'geschlecht', 'geb_datum', 'bezirk_id', 'no_automatic_delete'];
                    $this->settingsGateway->logChangedSetting($this->session->id(), $oldFs, $data, $logChangedFields);
                }

                if (!isset($data['bezirk_id'])) {
                    $data['bezirk_id'] = $this->session->getCurrentRegionId();
                }
                if ($this->foodsaverGateway->updateProfile($this->session->id(), $data)) {
                    try {
                        $this->session->refreshFromDatabase();
                        $this->flashMessageHelper->success($this->translator->trans('foodsaver.edit_success'));
                    } catch (\Exception) {
                        $this->routeHelper->goPageAndExit('logout');
                    }
                } else {
                    $this->flashMessageHelper->error($this->translator->trans('error_unexpected'));
                }
            }
        }
    }

    private function validUrl($url)
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        return true;
    }

    private function picture_box(): string
    {
        $photo = $this->foodsaverGateway->getPhotoFileName($this->session->id());

        return $this->view->picture_box($photo);
    }

    private function handle_newmail()
    {
        if ($email = $this->settingsGateway->getNewMail($this->session->id(), $_GET['newmail'])) {
            $this->pageHelper->addJs("ajreq('changemail3');");
        } else {
            $this->flashMessageHelper->info($this->translator->trans('foodsaver.mailchange_error'));
        }
    }
}
