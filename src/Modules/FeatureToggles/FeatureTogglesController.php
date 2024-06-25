<?php

namespace Foodsharing\Modules\FeatureToggles;

use Foodsharing\Lib\FoodsharingController;
use Foodsharing\Modules\Development\FeatureToggles\Querys\HasPermissionToManageFeatureTogglesQuery;
use Foodsharing\Modules\Development\FeatureToggles\Services\FeatureToggleService;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

final class FeatureTogglesController extends FoodsharingController
{
    public function __construct()
    {
        parent::__construct();
    }

    #[Route('/featuretoggles')]
    public function index(
        FeatureToggleService $featureToggleService,
        HasPermissionToManageFeatureTogglesQuery $hasPermissionToManageFeatureTogglesQuery,
    ): Response {
        $this->pageHelper->addTitle('FeatureToggle Management');

        if (!$hasPermissionToManageFeatureTogglesQuery->execute($this->session, $this->currentUserUnits)) {
            $this->routeHelper->goLoginAndExit();
        }

        $featureToggleService->updateFeatureToggles();

        $featureTogglePage = $this->prepareVueComponent('vue-feature-toggles', 'FeatureToggles');
        $this->pageHelper->addContent($featureTogglePage);

        return $this->renderGlobal();
    }
}
