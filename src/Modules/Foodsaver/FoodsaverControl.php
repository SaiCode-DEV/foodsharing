<?php

namespace Foodsharing\Modules\Foodsaver;

use Foodsharing\Modules\Core\Control;
use Foodsharing\Modules\Core\DBConstants\Foodsaver\Role;
use Foodsharing\Modules\Region\RegionGateway;
use Foodsharing\Modules\Settings\SettingsGateway;
use Foodsharing\Permissions\ProfilePermissions;
use Foodsharing\Utility\DataHelper;
use Foodsharing\Utility\IdentificationHelper;

class FoodsaverControl extends Control
{
    public function __construct(
        FoodsaverView $view,
        private readonly FoodsaverGateway $foodsaverGateway,
        private readonly FoodsaverTransactions $foodsaverTransactions,
        private readonly RegionGateway $regionGateway,
        private readonly SettingsGateway $settingsGateway,
        private readonly ProfilePermissions $profilePermissions,
        private readonly DataHelper $dataHelper,
        private readonly IdentificationHelper $identificationHelper
    ) {
        $this->view = $view;
        parent::__construct();
    }

    /*
     * Default Method for /?page=foodsaver
     */
    public function index()
    {
        $fsId = $this->identificationHelper->getActionId('edit'); // int or false
        if (!$fsId) {
            $this->routeHelper->goAndExit('/');
        } if (!$this->profilePermissions->mayAdministrateUserProfile($fsId)) {
            $this->pageHelper->addContent(
                $this->v_utils->v_info($this->translator->trans('foodsaver.restricted'))
            );

            return;
        }
        // begin user-edit
        if (!$fs = $this->foodsaverGateway->getFoodsaver($fsId)) {
            $this->pageHelper->addContent(
                $this->v_utils->v_info($this->translator->trans('foodsaver.restricted'))
            );

            return;
        }
        $this->handle_edit();
        $fs = $this->foodsaverGateway->getFoodsaver($fsId); // refresh data as it may changed

        $name = $fs['name'] . ' ' . $fs['nachname'];
        $regionDetails = $fs['bezirk_id'] > 0 ? $this->regionGateway->getRegion($fs['bezirk_id']) : false;

        $this->pageHelper->addBread($name, '/profile/' . $fs['id']);
        $this->pageHelper->addBread($this->translator->trans('foodsaver.edit'));

        $this->dataHelper->setEditData($fs);
        $this->pageHelper->addContent(
            $this->view->foodsaver_form(
                $this->translator->trans('foodsaver.editName', ['{name}' => $name]),
                $regionDetails
            )
        );

        $actions = [];
        if ($this->session->mayRole()) {
            $actions[] = [
                'href' => '/profile/' . $fs['id'],
                'name' => $this->translator->trans('foodsaver.profileBack'),
            ];
        }
        if ($this->profilePermissions->mayDeleteUser($fs['id'])) {
            $actions[] = [
                'click' => ($fsId == $this->session->id())
                    ? 'confirmDeleteSelf(' . $fs['id'] . ')'
                    : 'confirmDeleteUser(' . $fs['id'] . ',\'' . $name . '\')',
                'name' => '⚠️ ' . $this->translator->trans('foodsaver.delete_account'),
            ];
        }
        $this->pageHelper->addContent(
            $this->v_utils->v_field(
                $this->v_utils->v_menu($actions, $this->translator->trans('foodsaver.actions')),
            ),
            CNT_RIGHT
        );
    }

    private function handle_edit(): void
    {
        global $g_data;

        if ($this->submitted()) {
            $g_data['stadt'] = $g_data['ort'];
            if ($this->session->mayRole(Role::ORGA)) {
                if (isset($g_data['orgateam']) && is_array($g_data['orgateam']) && $g_data['orgateam'][0] == 1) {
                    $g_data['orgateam'] = 1;
                }
            } else {
                $g_data['orgateam'] = 0;
                unset($g_data['email'], $g_data['rolle']);
            }

            if (isset($_GET['id']) && $fsId = (int)$_GET['id']) {
                if ($oldFs = $this->foodsaverGateway->getFoodsaver($fsId)) {
                    $changedFields = [
                        'name',
                        'nachname',
                        'stadt',
                        'plz',
                        'anschrift',
                        'telefon',
                        'handy',
                        'geschlecht',
                        'geb_datum',
                        'rolle',
                        'orgateam',
                        'bezirk_id',
                        'no_automatic_delete'
                    ];
                    $this->settingsGateway->logChangedSetting(
                        $fsId,
                        $oldFs,
                        $g_data,
                        $changedFields,
                        $this->session->id()
                    );
                }

                if (!isset($g_data['bezirk_id'])) {
                    $g_data['bezirk_id'] = $this->currentUserUnits->getCurrentRegionId();
                }

                if ($this->updateFoodsaver($oldFs, $g_data)) {
                    $this->flashMessageHelper->success($this->translator->trans('foodsaver.edit_success'));
                } else {
                    $this->flashMessageHelper->error($this->translator->trans('foodsaver.edit_failure'));
                }
            }
        }
    }

    private function updateFoodsaver(array $fs, array $data): bool
    {
        if (!$this->session->mayRole(Role::ORGA)) {
            unset($data['rolle']);
        }

        if (isset($data['rolle']) && $data['rolle'] == Role::FOODSHARER->value && $data['rolle'] < $fs['rolle']) {
            $downgradedRows = $this->foodsaverTransactions->downgradeAndBlockForQuizPermanently($fs['id']);
        } else {
            $downgradedRows = 0;
        }

        $updatedRows = $this->foodsaverGateway->updateFoodsaver($fs['id'], $data);

        return $downgradedRows > 0 || $updatedRows > 0;
    }
}
