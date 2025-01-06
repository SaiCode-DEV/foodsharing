<?php

namespace Foodsharing\Modules\Application;

use Foodsharing\Lib\FoodsharingController;
use Foodsharing\Modules\Core\DatabaseNoValueFoundException;
use Foodsharing\Modules\Region\RegionGateway;
use Foodsharing\Permissions\ApplicationPermissions;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

class ApplicationController extends FoodsharingController
{
    public function __construct(
        private readonly ApplicationGateway $gateway,
        private readonly RegionGateway $regionGateway,
        private readonly ApplicationView $view,
        private readonly ApplicationPermissions $applicationPermissions,
    ) {
        parent::__construct();
    }

    #[Route(path: '/regions/{groupId}/applications/{userId}', name: 'applications', requirements: [
        'groupId' => Requirement::POSITIVE_INT,
        'userId' => Requirement::POSITIVE_INT
    ])]
    public function index(int $groupId, int $userId): Response
    {
        try {
            $groupName = $this->regionGateway->getRegionName($groupId);
        } catch (DatabaseNoValueFoundException) {
            // region does not exist
            $this->routeHelper->goAndExit('/');
        }
        $this->view->setGroupName($groupId, $groupName);

        if (!$this->applicationPermissions->maySeeApplications($groupId)) {
            $this->routeHelper->goAndExit('/');
        }

        $application = $this->gateway->getApplication($groupId, $userId);
        if (!$application) {
            $this->routeHelper->goAndExit("/region?bid={$groupId}&sub=applications");
        }
        $this->pageHelper->addBread($groupName, '/region?bid=' . $groupId);
        $this->pageHelper->addBread($this->translator->trans('group.application_from') . $application->applicant->name, '');
        $this->pageHelper->addContent($this->view->application($application));
        $this->pageHelper->addContent($this->view->applicationMenu($application->applicant), CNT_LEFT);

        return $this->renderGlobal();
    }
}
