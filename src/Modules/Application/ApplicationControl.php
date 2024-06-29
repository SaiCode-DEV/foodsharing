<?php

namespace Foodsharing\Modules\Application;

use Foodsharing\Modules\Core\Control;
use Foodsharing\Modules\Core\DBConstants\Foodsaver\Role;
use Foodsharing\Modules\Region\RegionGateway;
use Foodsharing\Utility\IdentificationHelper;

class ApplicationControl extends Control
{
    private string $groupName;
    private int $groupId;

    public function __construct(
        private readonly ApplicationGateway $gateway,
        private readonly RegionGateway $regionGateway,
        ApplicationView $view,
        private readonly IdentificationHelper $identificationHelper
    ) {
        $this->view = $view;

        parent::__construct();
        if (($this->groupId = $this->identificationHelper->getGetId('bid')) === false) {
            $this->groupId = $this->currentUserUnits->getCurrentRegionId() ?? 0;
        }

        $mayManageApplications = ($this->currentUserUnits->isAdminFor($this->groupId) || $this->session->mayRole(Role::ORGA));
        if (!$mayManageApplications) {
            $this->routeHelper->goAndExit('/');
        }

        $this->groupName = $this->regionGateway->getRegionName($this->groupId);
        $this->view->setGroupName($this->groupId, $this->groupName);
    }

    public function index(): void
    {
        $application = $this->gateway->getApplication($this->groupId, $_GET['fid']);
        if (!$application) {
            return;
        }
        $this->pageHelper->addBread($this->groupName, '/region?bid=' . $this->groupId);
        $this->pageHelper->addBread($this->translator->trans('group.application_from') . $application->applicant->name, '');
        $this->pageHelper->addContent($this->view->application($application));

        $this->pageHelper->addContent($this->view->applicationMenu($application->applicant), CNT_LEFT);
    }
}
