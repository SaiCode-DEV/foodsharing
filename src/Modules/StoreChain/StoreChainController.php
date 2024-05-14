<?php

namespace Foodsharing\Modules\StoreChain;

use Foodsharing\Lib\FoodsharingController;
use Foodsharing\Modules\Core\DBConstants\Foodsaver\Role;
use Foodsharing\Modules\Core\DBConstants\Region\RegionIDs;
use Foodsharing\Permissions\StoreChainPermissions;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class StoreChainController extends FoodsharingController
{
    public function __construct(
        private readonly StoreChainPermissions $permissions
    ) {
        parent::__construct();

        if (!$this->session->mayRole()) {
            $this->routeHelper->goLoginAndExit();
        }
    }

    #[Route('/chain', name: 'chain_index')]
    public function chainIndex(): Response
    {
        if (!$this->permissions->maySeeChainList()) {
            $this->flashMessageHelper->info($this->translator->trans('chain.error.notfs'));
            $this->routeHelper->goAndExit('?page=settings&sub=rise_role&role=' . Role::FOODSAVER->value);
        }

        $this->pageHelper->addBread($this->translator->trans('chain.bread.workinggroup'), '/region?bid=' . RegionIDs::STORE_CHAIN_GROUP);
        $this->pageHelper->addBread($this->translator->trans('chain.bread.list'), '/#');
        $this->pageHelper->addTitle($this->translator->trans('chain.pagetitle'));

        $storeChainPage = $this->prepareVueComponent('vue-chainlist', 'chain-list', [
            'adminPermissions' => $this->permissions->mayAdministrateStoreChains(),
            'ownId' => $this->session->id(),
        ]);
        $this->pageHelper->addContent($storeChainPage);

        return $this->renderGlobal();
    }
}
