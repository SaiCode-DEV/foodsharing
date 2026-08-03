<?php

namespace Foodsharing\Modules\Store;

use Exception;
use Foodsharing\Lib\FoodsharingController;
use Foodsharing\Modules\Core\DBConstants\Unit\UnitType;
use Foodsharing\Modules\Region\RegionGateway;
use Foodsharing\Permissions\StorePermissions;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class StoreController extends FoodsharingController
{
    public function __construct(
        private readonly RegionGateway $regionGateway,
        private readonly StorePermissions $storePermissions,
    ) {
        parent::__construct();
    }

    #[Route(path: '/betrieb', name: 'store_fallback')]
    public function storesFallback(Request $request): RedirectResponse
    {
        $action = $request->query->get('a');
        $regionId = $request->query->get('bid');

        if (empty($action) && !empty($regionId)) {
            return $this->redirectToRoute('region_stores', ['regionId' => $regionId]);
        }

        if ($action === 'own') {
            return $this->redirectToRoute('store_own', ['userId' => $this->session->id()]);
        }

        if ($action === 'new') {
            return $this->redirectToRoute('store_new', ['regionId' => $regionId]);
        }

        return $this->redirect('/');
    }

    #[Route(path: '/region/{regionId}/stores', name: 'region_stores', requirements: ['regionId' => '[0-9]+'])]
    public function regionStores(int $regionId): Response
    {
        if (!$this->session->mayRole() || !$this->storePermissions->mayListStores()) {
            return $this->redirectToRoute('/');
        }

        if ($regionId > 0) {
            $region = $this->regionGateway->getRegion($regionId);
        } else {
            $region = ['name' => $this->translator->trans('store.complete')];
        }

        if (empty($region) || $regionId <= 0) {
            $this->flashMessageHelper->info($this->translator->trans('store.error'));

            return $this->redirectToRoute('/');
        } else {
            $this->pageHelper->addBread($region['name'], '/region?bid=' . $regionId);
            $this->pageHelper->addBread($this->translator->trans('store.bread'), '/?page=fsbetrieb');
            $this->pageHelper->addContent($this->prepareVueComponent('vue-store-region-list', 'store-region-list', [
                'regionName' => $region['name'],
                'regionId' => $regionId,
                'showCreateStore' => $this->storePermissions->mayCreateStore()
            ]));
        }

        return $this->renderGlobal();
    }

    #[Route(path: '/user/{userId}/stores', name: 'store_own')]
    public function ownStores(int $userId): Response
    {
        $this->pageHelper->addBread($this->translator->trans('store.ownStores'));
        $this->pageHelper->addContent($this->prepareVueComponent('vue-store-user-list', 'StoreUserList', [
            'userId' => $userId
        ]));

        return $this->renderGlobal();
    }

    /**
     * @throws Exception
     */
    #[Route(path: '/region/{regionId}/store/new', name: 'store_new')]
    public function newStore(int $regionId): Response
    {
        if ($this->storePermissions->mayCreateStore()) {
            $this->pageHelper->addBread($this->translator->trans('storeedit.add-new'), '/?page=fsbetrieb');

            if ($regionId > 0) {
                $region = $this->regionGateway->getRegion($regionId);
            } else {
                $region = ['name' => $this->translator->trans('store.complete')];
            }

            $chosenRegion = ($regionId > 0 && UnitType::isAccessibleRegion($this->regionGateway->getType($regionId))) ? $region : null;

            $this->pageHelper->addContent($this->prepareVueComponent('vue-store-new', 'StoreNew', [
                'chosenRegion' => $chosenRegion,
            ]));

            return $this->renderGlobal();
        } else {
            $this->flashMessageHelper->info($this->translator->trans('store.smneeded'));

            return $this->redirectToRoute('current_user_settings', ['sub' => 'up_bip']);
        }
    }
}
