<?php

namespace Foodsharing\Modules\Team;

use Foodsharing\Lib\FoodsharingController;
use Foodsharing\Modules\Core\DBConstants\Region\RegionIDs;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class TeamController extends FoodsharingController
{
    public function __construct(
        private readonly TeamGateway $gateway,
    ) {
        parent::__construct();
    }

    // displays the board
    #[Route('/team', name: 'team')]
    public function index(): Response
    {
        $this->pageHelper->addBread($this->translator->trans('team.current'), '/team');
        $this->pageHelper->addTitle($this->translator->trans('team.current'));

        $params = $this->createTeamMemberObject();

        $teamPage = $this->prepareVueComponent('vue-team-page', 'TeamPage', $params);
        $this->pageHelper->addContent($teamPage);

        return $this->renderGlobal();
    }

    private function createTeamMemberObject(): array
    {
        return [
            'teamBoardMember' => $this->getTeamMember(RegionIDs::TEAM_BOARD_MEMBER),
            'teamAdministrationMember' => $this->getTeamMember(RegionIDs::TEAM_ADMINISTRATION_MEMBER),
            'teamAlumniMember' => $this->getTeamMember(RegionIDs::TEAM_ALUMNI_MEMBER)
        ];
    }

    private function getTeamMember(int $regionId): array
    {
        if ($team = $this->gateway->getTeam($regionId)) {
            shuffle($team);
        }

        return $team ?: [];
    }
}
