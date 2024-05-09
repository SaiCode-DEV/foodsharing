<?php

namespace Foodsharing\Modules\StoreChain;

use Foodsharing\Modules\Core\Control;
use Foodsharing\Modules\Core\DBConstants\Foodsaver\Role;
use Foodsharing\Modules\Core\DBConstants\Region\RegionIDs;
use Foodsharing\Modules\Core\View;
use Foodsharing\Permissions\StoreChainPermissions;

class StoreChainControl extends Control
{
    public function __construct(
        View $view,
        private readonly StoreChainPermissions $permissions
    ) {
        $this->view = $view;

        parent::__construct();

        if (!$this->session->mayRole()) {
            $this->routeHelper->goLoginAndExit();
        }
    }

    public function index()
    {
        if (!$this->permissions->maySeeChainList()) {
            $this->flashMessageHelper->info($this->translator->trans('chain.error.notfs'));
            $this->routeHelper->goAndExit('?page=settings&sub=rise_role&role=' . Role::FOODSAVER->value);
        }

        $this->pageHelper->addBread($this->translator->trans('chain.bread.workinggroup'), '/region?bid=' . RegionIDs::STORE_CHAIN_GROUP);
        $this->pageHelper->addBread($this->translator->trans('chain.bread.list'), '/#');
        $this->pageHelper->addTitle($this->translator->trans('chain.pagetitle'));

        $this->pageHelper->addContent($this->view->vueComponent('vue-chainlist', 'chain-list', [
            'adminPermissions' => $this->permissions->mayAdministrateStoreChains(),
            'ownId' => $this->session->id(),
        ]));
    }
}
