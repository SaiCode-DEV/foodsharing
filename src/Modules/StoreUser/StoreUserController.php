<?php

namespace Foodsharing\Modules\StoreUser;

use Foodsharing\Lib\FoodsharingController;
use Foodsharing\Modules\Store\StoreGateway;
use Foodsharing\Permissions\StorePermissions;
use FOS\RestBundle\Controller\Annotations\QueryParam;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class StoreUserController extends FoodsharingController
{
    #[Route(path: '/store/{storeId}', name: 'store.show', requirements: ['storeId' => '\d+'], methods: ['GET'])]
    #[QueryParam(name: 'showTeamRequests', description: 'The store page will open the modal with the pending team requests')]
    public function index(
        int $storeId,
        StoreGateway $storeGateway,
        StorePermissions $storePermissions,
        Request $request,
    ): Response {
        if (!$this->session->mayRole()) {
            $this->routeHelper->goLoginAndExit();
        }

        if (!$storeGateway->storeExists($storeId)) {
            return $this->redirect('/?page=dashboard');
        }

        if (!$storePermissions->mayAccessStore($storeId)) {
            $this->flashMessageHelper->info($this->translator->trans('store.not-in-team'));

            return $this->redirect('/karte?bid=' . $storeId);
        }

        $params['storeId'] = $storeId;
        $params['storeManagers'] = $storeGateway->getStoreManagers($storeId);

        if ($request->query->has('showTeamRequests')) {
            $params['showTeamRequests'] = true;
        }

        $this->pageHelper->addTitle($storeGateway->getStoreName($storeId));
        $vue = $this->prepareVueComponent('vue-store-page', 'StorePage', $params);
        $this->pageHelper->addContent($vue);

        return $this->renderGlobal();
    }
}
