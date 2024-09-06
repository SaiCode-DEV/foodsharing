<?php

namespace Foodsharing\Modules\StoreCategories;

use Foodsharing\Lib\FoodsharingController;
use Foodsharing\Permissions\StoreCategoriesPermissions;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class StoreCategoriesController extends FoodsharingController
{
    public function __construct(
        private readonly StoreCategoriesPermissions $storeCategoriesPermissions,
    ) {
        parent::__construct();
    }

    #[Route(path: '/storecategories', name: 'store_categories')]
    public function index(Request $request): Response
    {
        if (!$this->session->mayRole()) {
            $this->routeHelper->goLoginAndExit();
        }
        if (!$this->storeCategoriesPermissions->mayEditStoreCategories()) {
            $this->routeHelper->goAndExit('/?page=dashboard');
        }

        $this->pageHelper->addTitle($this->translator->trans('store_categories.title'));
        $this->pageHelper->addContent($this->prepareVueComponent('store-categories-list', 'StoreCategoriesList'));

        return $this->renderGlobal();
    }
}
