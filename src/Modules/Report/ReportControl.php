<?php

namespace Foodsharing\Modules\Report;

use Foodsharing\Modules\Core\Control;
use Foodsharing\Permissions\ReportPermissions;
use Foodsharing\Utility\ImageHelper;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ReportControl extends Control
{
    private readonly ReportGateway $reportGateway;
    private readonly ImageHelper $imageService;
    private readonly ReportPermissions $reportPermissions;

    public function __construct(
        ReportGateway $reportGateway,
        ReportView $view,
        ImageHelper $imageService,
        ReportPermissions $reportPermissions)
    {
        $this->reportGateway = $reportGateway;
        $this->view = $view;
        $this->imageService = $imageService;
        $this->reportPermissions = $reportPermissions;

        parent::__construct();

        if (!$this->session->mayRole()) {
            $this->routeHelper->goLoginAndExit();
        }
    }

    public function index(Request $request, Response $response): void
    {
        if (isset($_GET['bid'])) {
            $this->byRegion($_GET['bid'], $response);
        } else {
            if ($this->reportPermissions->mayHandleReports()) {
                $this->pageHelper->addBread($this->translator->trans('menu.reports'), '/?page=report');
            } else {
                $this->routeHelper->goAndExit('/?page=dashboard');
            }
        }
    }

    private function byRegion($regionId, $response): void
    {
        $response->setContent($this->render('pages/Report/by-region.twig',
            ['bid' => $regionId]
        ));
    }

    public function foodsaver(): void
    {
        if ($this->reportPermissions->mayHandleReports()) {
            if ($foodsaver = $this->reportGateway->getReportedSaver($_GET['id'])) {
                $this->pageHelper->addBread(
                    $this->translator->trans('menu.reports'),
                    '/?page=report&sub=foodsaver&id=' . (int)$foodsaver['id']
                );
                $this->pageHelper->addJs(
                    '
						$(".welcome_profile_image").css("cursor","pointer");
						$(".welcome_profile_image").on("click", function(){
							$(".user_display_name a").trigger("click");
						});
				'
                );
                $this->pageHelper->addContent(
                    $this->view->topbar(
                        $this->translator->trans('profile.report.control.from') . ' <a href="/profile/' . (int)$foodsaver['id'] . '">' . $foodsaver['name'] . ' ' . $foodsaver['nachname'] . '</a>',
                        \count($foodsaver['reports']) . ' ' . $this->translator->trans('profile.report.control.tot'),
                        $this->imageService->avatar($foodsaver, 50)
                    ),
                    CNT_TOP
                );
                $this->pageHelper->addContent($this->view->vueComponent('vue-wall', 'wall', [
                    'target' => 'fsreports',
                    'targetId' => (int)$_GET['id'],
                    'title' => $this->translator->trans('profile.report.control.notes')
                ]));
                $this->pageHelper->addContent(
                    $this->view->listReportsTiny($foodsaver['reports']),
                    CNT_RIGHT
                );
            }
        } else {
            $this->routeHelper->goAndExit('/?page=dashboard');
        }
    }
}
