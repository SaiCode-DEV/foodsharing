<?php

namespace Foodsharing\Modules\Store;

use Foodsharing\Modules\Core\Control;
use Foodsharing\Modules\Core\DBConstants\Foodsaver\Role;
use Foodsharing\Modules\Core\DBConstants\Unit\UnitType;
use Foodsharing\Modules\Region\RegionGateway;
use Foodsharing\Modules\Store\DTO\CreateStoreData;
use Foodsharing\Permissions\StorePermissions;
use Foodsharing\Utility\IdentificationHelper;

class StoreControl extends Control
{
    private $storePermissions;
    private $storeTransactions;
    private $regionGateway;
    private $identificationHelper;

    public function __construct(
        StorePermissions $storePermissions,
        StoreTransactions $storeTransactions,
        StoreView $view,
        RegionGateway $regionGateway,
        IdentificationHelper $identificationHelper,
    ) {
        $this->view = $view;
        $this->storePermissions = $storePermissions;
        $this->storeTransactions = $storeTransactions;
        $this->regionGateway = $regionGateway;
        $this->identificationHelper = $identificationHelper;

        parent::__construct();

        if (!$this->session->mayRole()) {
            $this->routeHelper->goLoginAndExit();
        }
    }

    public function index()
    {
        /* form methods below work with $g_data */
        global $g_data;

        if (isset($_GET['bid'])) {
            $regionId = (int)$_GET['bid'];
        } else {
            $regionId = $this->session->getCurrentRegionId();
        }

        if (!$this->session->mayRole(Role::ORGA) && $regionId == 0) {
            $regionId = $this->session->getCurrentRegionId();
        }
        if ($regionId > 0) {
            $region = $this->regionGateway->getRegion($regionId);
        } else {
            $region = ['name' => $this->translator->trans('store.complete')];
        }
        if ($this->identificationHelper->getAction('new')) {
            if ($this->storePermissions->mayCreateStore()) {
                $this->handle_add($this->session->id());

                $this->pageHelper->addBread($this->translator->trans('storeedit.add-new'), '/?page=fsbetrieb');

                $chosenRegion = ($regionId > 0 && UnitType::isAccessibleRegion($this->regionGateway->getType($regionId))) ? $region : null;
                $this->pageHelper->addContent($this->view->betrieb_form(
                    $this->storeTransactions->getCommonStoreMetadata(false),
                    $chosenRegion,
                    'betrieb'
                ));

                $this->pageHelper->addContent($this->v_utils->v_field($this->v_utils->v_menu([
                    ['name' => $this->translator->trans('bread.backToOverview'), 'href' => '/?page=fsbetrieb&bid=' . $regionId]
                ]), $this->translator->trans('storeedit.actions')), CNT_RIGHT);
            } else {
                $this->flashMessageHelper->info($this->translator->trans('store.smneeded'));
                $this->routeHelper->goAndExit('/?page=settings&sub=up_bip');
            }
        } elseif ($this->identificationHelper->getAction('own')) {
            $this->pageHelper->addBread($this->translator->trans('store.ownStores'));
            $this->pageHelper->addContent($this->view->storeOwnList());
        } elseif (isset($_GET['id'])) {
            $this->routeHelper->goAndExit('/?page=fsbetrieb&id=' . (int)$_GET['id']);
        } else {
            if (!$this->session->mayRole() || !$this->storePermissions->mayListStores()) {
                $this->routeHelper->goAndExit('/');
            }

            if (empty($region) || $regionId <= 0) {
                $this->flashMessageHelper->info($this->translator->trans('store.error'));
                $this->routeHelper->goAndExit('/');
            } else {
                $this->pageHelper->addBread($this->translator->trans('store.bread'), '/?page=fsbetrieb');
                $this->pageHelper->addContent($this->view->vueComponent('vue-store-region-list', 'store-region-list', [
                    'regionName' => $region['name'],
                    'regionId' => $regionId,
                    'showCreateStore' => $this->storePermissions->mayCreateStore()
                ]));
            }
        }
    }

    private function handle_add($coordinator)
    {
        global $g_data;
        if (!$this->submitted()) {
            return;
        }

        $g_data['bezirk_id'] ??= $this->session->getCurrentRegionId();

        if (!$this->session->mayBezirk($g_data['bezirk_id'])) {
            $this->flashMessageHelper->error($this->translator->trans('storeedit.not-in-region'));
            $this->routeHelper->goPageAndExit();
        }

        if (isset($g_data['ort'])) {
            $g_data['stadt'] = $g_data['ort'];
        }
        if (isset($g_data['anschrift'])) {
            $g_data['str'] = $g_data['anschrift'];
        }
        $firstStorePost = $g_data['first_post'] ?? null;
        $storeId = $this->storeTransactions->createStore(CreateStoreData::createFromArray($g_data), $this->session->id(), $firstStorePost);

        if (!$storeId) {
            $this->flashMessageHelper->error($this->translator->trans('error_unexpected'));

            return;
        }

        $this->flashMessageHelper->success($this->translator->trans('storeedit.add_success'));

        $this->routeHelper->goAndExit('/?page=fsbetrieb&id=' . (int)$storeId);
    }
}
