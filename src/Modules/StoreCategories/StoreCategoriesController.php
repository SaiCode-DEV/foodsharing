<?php

namespace Foodsharing\Modules\StoreCategories;

use Foodsharing\Lib\FoodsharingController;
use Foodsharing\Permissions\StoreCategoriesPermissions;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class StoreCategoriesController extends FoodsharingController
{
    public function __construct(
        private readonly StoreCategoriesPermissions $storeCategoriesPermissions,
    ) {
        parent::__construct();
    }

    #[Route(path: '/storecategories', name: 'store_categories')]
    public function index(): Response
    {
        if (!$this->session->mayRole()) {
            $this->routeHelper->goLoginAndExit();
        }
        if (!$this->storeCategoriesPermissions->mayEditStoreCategories()) {
            return $this->redirectToRoute('dashboard');
        }
        $this->pageHelper->addTitle($this->translator->trans('store_categories.title'));
        $this->pageHelper->addContent($this->prepareVueComponent('store-categories-list', 'StoreCategoriesList'));

        return $this->renderGlobal();
    }
}
