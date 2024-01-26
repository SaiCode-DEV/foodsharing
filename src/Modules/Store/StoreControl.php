<?php

namespace Foodsharing\Modules\Store;

use Foodsharing\Modules\Core\Control;
use Foodsharing\Modules\Core\DBConstants\Foodsaver\Role;
use Foodsharing\Modules\Core\DBConstants\Unit\UnitType;
use Foodsharing\Modules\Core\DTO\PatchGeoLocation;
use Foodsharing\Modules\Region\RegionGateway;
use Foodsharing\Modules\Store\DTO\CreateStoreData;
use Foodsharing\Modules\Store\DTO\PatchAddress;
use Foodsharing\Modules\Store\DTO\PatchContactData;
use Foodsharing\Modules\Store\DTO\PatchStore;
use Foodsharing\Modules\Store\DTO\PatchStoreOptionModel;
use Foodsharing\Permissions\StorePermissions;
use Foodsharing\Utility\DataHelper;
use Foodsharing\Utility\IdentificationHelper;

class StoreControl extends Control
{
    private $storeGateway;
    private $storePermissions;
    private $storeTransactions;
    private $regionGateway;
    private $identificationHelper;
    private $dataHelper;

    public function __construct(
        StorePermissions $storePermissions,
        StoreTransactions $storeTransactions,
        StoreView $view,
        StoreGateway $storeGateway,
        RegionGateway $regionGateway,
        IdentificationHelper $identificationHelper,
        DataHelper $dataHelper
    ) {
        $this->view = $view;
        $this->storeGateway = $storeGateway;
        $this->storePermissions = $storePermissions;
        $this->storeTransactions = $storeTransactions;
        $this->regionGateway = $regionGateway;
        $this->identificationHelper = $identificationHelper;
        $this->dataHelper = $dataHelper;

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
        } elseif ($id = $this->identificationHelper->getActionId('edit')) {
            $this->pageHelper->addBread($this->translator->trans('storeedit.bread'), '/?page=fsbetrieb');
            $this->pageHelper->addBread($this->translator->trans('storeedit.bread'));
            $store = $this->storeGateway->getStore($id);
            $data['name'] = $store->name;
            $data['bezirk_id'] = $store->region->id;
            $data['lat'] = $store->location->lat;
            $data['lon'] = $store->location->lon;
            $data['str'] = $store->address->street;
            $data['plz'] = $store->address->zipCode;
            $data['ort'] = $store->address->city;

            $data['public_info'] = $store->publicInfo;
            $data['public_time'] = $store->publicTime->value;
            $data['betrieb_kategorie_id'] = $store->category ? $store->category->id : null;
            $data['kette_id'] = $store->chain ? $store->chain->id : null;
            $data['betrieb_status_id'] = $store->cooperationStatus->value;
            $data['besonderheiten'] = $store->description;

            $data['ansprechpartner'] = $store->contact->name;
            $data['telefon'] = $store->contact->phone;
            $data['fax'] = $store->contact->fax;
            $data['email'] = $store->contact->email;

            $data['begin'] = $store->cooperationStart ? $store->cooperationStart->format('Y-m-d') : '';
            $data['prefetchtime'] = $store->calendarInterval;
            $data['abholmenge'] = $store->weight;
            $data['ueberzeugungsarbeit'] = $store->effort->value;
            $data['presse'] = $store->publicity;
            $data['sticker'] = $store->showsSticker;
            $data['use_region_pickup_rule'] = $store->options->useRegionPickupRule;
            $data['lebensmittel'] = $store->groceries;
            $data['status_date'] = $store->updatedAt->format('Y-m-d H:i:s');
            $data['added'] = $store->createdAt->format('Y-m-d H:i:s');

            $this->pageHelper->addTitle($store->name);
            $this->pageHelper->addTitle($this->translator->trans('storeedit.bread'));

            if ($this->storePermissions->mayEditStore($id)) {
                $this->handle_edit();

                $this->dataHelper->setEditData($data);

                $regionId = $store->region->id;
                $regionName = $this->regionGateway->getRegionName($regionId);

                $this->pageHelper->addContent($this->view->betrieb_form(
                    $this->storeTransactions->getCommonStoreMetadata(false),
                    ['id' => $regionId, 'name' => $regionName],
                    '',
                ));
            } else {
                $this->flashMessageHelper->info($this->translator->trans('store.locked'));
            }

            $this->pageHelper->addContent($this->v_utils->v_field($this->v_utils->v_menu([
                ['name' => $this->translator->trans('bread.backToStore'), 'href' => '/?page=fsbetrieb&bid=' . $regionId]
            ]), $this->translator->trans('storeedit.actions')), CNT_RIGHT);
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

    private function handle_edit()
    {
        global $g_data;
        if ($this->submitted()) {
            $id = (int)$_GET['id'];
            $store = new PatchStore();
            $store->name = $g_data['name'];
            $store->regionId = intval($g_data['bezirk_id']);

            $store->location = new PatchGeoLocation();
            $store->location->lat = floatval($g_data['lat']);
            $store->location->lon = floatval($g_data['lon']);
            $store->address = new PatchAddress();
            $store->address->street = $g_data['anschrift'];
            $store->address->zipCode = $g_data['plz'];
            $store->address->city = $g_data['ort'];

            $store->publicInfo = $g_data['public_info'];
            $store->publicTime = intval($g_data['public_time']);

            if (!empty($g_data['betrieb_kategorie_id'])) {
                $store->categoryId = intval($g_data['betrieb_kategorie_id']);
            }
            if (!empty($g_data['kette_id'])) {
                $store->chainId = intval($g_data['kette_id']);
            }
            $store->cooperationStatus = intval($g_data['betrieb_status_id']);
            $store->description = $g_data['besonderheiten'];

            $store->contact = new PatchContactData();
            $store->contact->name = $g_data['ansprechpartner'];
            $store->contact->phone = $g_data['telefon'];
            $store->contact->fax = $g_data['fax'];
            $store->contact->email = $g_data['email'];
            $store->cooperationStart = $g_data['begin'];
            $store->calendarInterval = intval($g_data['prefetchtime']);
            $store->weight = intval($g_data['abholmenge']);
            $store->effort = intval($g_data['ueberzeugungsarbeit']);
            $store->publicity = boolval($g_data['presse']);
            $store->showsSticker = boolval($g_data['sticker']);

            $store->options = new PatchStoreOptionModel();
            $store->options->useRegionPickupRule = boolval($g_data['use_region_pickup_rule']);
            if (isset($g_data['lebensmittel'])) {
                $store->groceries = array_map(fn ($value) => intval($value), $g_data['lebensmittel']);
            }

            try {
                $this->storeTransactions->updateStore($id, $store);
                $this->flashMessageHelper->success($this->translator->trans('storeedit.edit_success'));
            } catch (StoreTransactionException $ex) {
                $this->flashMessageHelper->error($ex->getMessage());
            }
            $this->routeHelper->goAndExit('/?page=fsbetrieb&id=' . $id);
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
