<?php

namespace Foodsharing\Modules\Categories;

use Foodsharing\Lib\FoodsharingController;
use Foodsharing\Modules\Core\DBConstants\CategoryType;
use Foodsharing\Permissions\CategoriesPermissions;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class CategoriesController extends FoodsharingController
{
    public function __construct(
        private readonly CategoriesPermissions $categoriesPermissions,
    ) {
        parent::__construct();
    }

    #[Route(path: '/categories/store', name: 'store_categories')]
    public function storeCategoriesPage(): Response
    {
        return $this->index(CategoryType::STORE);
    }

    #[Route(path: '/categories/resource', name: 'resource_categories')]
    public function resourceCategoriesPage(): Response
    {
        return $this->index(CategoryType::RESOURCE);
    }

    #[Route(path: '/categories/group', name: 'group_categories')]
    public function groupCategoriesPage(): Response
    {
        return $this->index(CategoryType::GROUP);
    }

    public function index(CategoryType $type): Response
    {
        if (!$this->session->mayRole()) {
            $this->routeHelper->goLoginAndExit();
        }
        if (!$this->categoriesPermissions->mayEditCategories($type)) {
            return $this->redirectToRoute('dashboard');
        }
        $this->pageHelper->addTitle($this->translator->trans('categories.title.' . $type->value));
        $this->pageHelper->addContent($this->prepareVueComponent('categories-editor', 'CategoriesEditor', [
            'categoryType' => $type->value,
        ]));

        return $this->renderGlobal();
    }
}
