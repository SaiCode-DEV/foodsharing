<?php

namespace Foodsharing\Modules\StoreUser;

use Foodsharing\Lib\FoodsharingController;
use Foodsharing\Modules\Store\StoreGateway;
use Foodsharing\Permissions\StorePermissions;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;

class StoreUserController extends FoodsharingController
{
    #[Route('/store', name: 'store.index')]
    public function legacyRedirect(#[MapQueryParameter] int $id): Response
    {
        return $this->redirectToRoute('store.show', ['storeId' => $id]);
    }

    #[Route(path: '/store/{storeId}', name: 'store.show', requirements: ['storeId' => '\d+'], methods: ['GET'])]
    public function index(
        int $storeId,
        StoreGateway $storeGateway,
        StorePermissions $storePermissions,
    ): Response {
        if (!$this->session->mayRole()) {
            $this->routeHelper->goLoginAndExit();
        }

        if (!$storeGateway->storeExists($storeId)) {
            return $this->redirectToRoute('dashboard');
        }

        if (!$storePermissions->mayAccessStore($storeId)) {
            $this->flashMessageHelper->info($this->translator->trans('store.not-in-team'));

            return $this->redirect('/karte?bid=' . $storeId);
        }

        $params['storeId'] = $storeId;

        $this->pageHelper->addTitle($storeGateway->getStoreName($storeId));
        $vue = $this->prepareVueComponent('vue-store-page', 'StorePage', $params);
        $this->pageHelper->addContent($vue);

        return $this->renderGlobal();
    }
}
