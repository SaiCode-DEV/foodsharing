<?php

namespace Foodsharing\Modules\Application;

use Foodsharing\Modules\Core\Control;
use Foodsharing\Modules\Core\DBConstants\Foodsaver\Role;
use Foodsharing\Utility\IdentificationHelper;

class ApplicationControl extends Control
{
    private $bezirk;
    private $bezirk_id = false;
    private readonly ApplicationGateway $gateway;
    private readonly IdentificationHelper $identificationHelper;

    public function __construct(
        ApplicationGateway $gateway,
        ApplicationView $view,
        IdentificationHelper $identificationHelper
    ) {
        $this->view = $view;
        $this->gateway = $gateway;
        $this->identificationHelper = $identificationHelper;

        parent::__construct();
        if (($this->bezirk_id = $this->identificationHelper->getGetId('bid')) === false) {
            $this->bezirk_id = $this->currentUserUnits->getCurrentRegionId() ?? 0;
        }

        $mayManageApplications = ($this->currentUserUnits->isAdminFor($this->bezirk_id) || $this->session->mayRole(Role::ORGA));
        if (!$mayManageApplications) {
            $this->routeHelper->goAndExit('/');
        }

        $this->bezirk = $this->gateway->getRegion($this->bezirk_id);
        $this->view->setBezirk($this->bezirk);
    }

    public function index(): void
    {
        $application = $this->gateway->getApplication($this->bezirk_id, $_GET['fid']);
        if (!$application) {
            return;
        }
        $this->pageHelper->addBread($this->bezirk['name'], '/region?bid=' . $this->bezirk_id);
        $this->pageHelper->addBread($this->translator->trans('group.application_from') . $application['name'], '');
        $this->pageHelper->addContent($this->view->application($application));

        $this->pageHelper->addContent($this->view->vueComponent('vue-wall', 'wall', [
            'target' => 'application',
            'targetId' => $application['id'],
            'title' => $this->translator->trans('storeview.status_notes')
        ]));

        $this->pageHelper->addContent($this->view->applicationMenu($application), CNT_LEFT);
    }
}
