<?php

namespace Foodsharing\Modules\Voting;

use Exception;
use Foodsharing\Modules\Core\Control;
use Foodsharing\Modules\Core\DBConstants\Unit\UnitType;
use Foodsharing\Modules\Region\RegionGateway;
use Foodsharing\Permissions\VotingPermissions;

class VotingControl extends Control
{
    public function __construct(
        private readonly VotingView $view,
        private readonly VotingGateway $votingGateway,
        private readonly VotingPermissions $votingPermissions,
        private readonly VotingTransactions $votingTransactions,
        private readonly RegionGateway $regionGateway
    ) {
        parent::__construct();
    }

    public function index()
    {
        try {
            if (isset($_GET['id']) && ($poll = $this->votingTransactions->getPoll($_GET['id'], true))
                && $this->votingPermissions->maySeePoll($poll)) {
                $region = $this->regionGateway->getRegion($poll->regionId);
                $this->pageHelper->addBread($region['name'], '/region?bid=' . $region['id']);
                $this->pageHelper->addBread($this->translator->trans('terminology.polls'), '/region?bid=' . $region['id'] . '&sub=polls');
                $this->pageHelper->addBread($poll->name);
                $this->pageHelper->addTitle($poll->name);

                if (isset($_GET['sub']) && $_GET['sub'] === 'edit') {
                    if ($this->votingPermissions->mayEditPoll($poll)) {
                        $this->pageHelper->addContent($this->view->editPollForm($poll));
                    } else {
                        $this->flashMessageHelper->error($this->translator->trans('poll.may_not_edit'));
                        $this->routeHelper->goAndExit('/?page=poll&id=' . $poll->id);
                    }
                } else {
                    $mayVote = $this->votingPermissions->mayVote($poll);
                    try {
                        $voteDateTime = $this->votingGateway->getVoteDatetime($poll->id, $this->session->id());
                    } catch (Exception) {
                        $voteDateTime = null;
                    }
                    $mayEdit = $this->votingPermissions->mayEditPoll($poll);
                    $this->pageHelper->addContent($this->view->pollOverview($poll, $region, $mayVote,
                        $mayVote ? null : $voteDateTime, $mayEdit)
                    );
                }
            } elseif (isset($_GET['sub']) && $_GET['sub'] === 'new' && isset($_GET['bid']) && ($region = $this->regionGateway->getRegion($_GET['bid']))
                && $this->votingPermissions->mayCreatePoll($region['id'])) {
                $this->pageHelper->addBread($region['name'], '/region?bid=' . $region['id']);
                $this->pageHelper->addBread($this->translator->trans('terminology.polls'), '/region?bid=' . $region['id'] . '&sub=polls');
                $this->pageHelper->addBread($this->translator->trans('polls.new_poll'));
                $this->pageHelper->addTitle($this->translator->trans('polls.new_poll'));

                $usersPerScope = $this->votingTransactions->getScopeCounts($region['id'], UnitType::isGroup($region['type']));

                $this->pageHelper->addContent($this->view->newPollForm($region, $usersPerScope));
            } else {
                $this->flashMessageHelper->info($this->translator->trans('poll.not_available'));
                $this->routeHelper->goAndExit('/dashboard');
            }
        } catch (Exception) {
            $this->flashMessageHelper->info($this->translator->trans('poll.not_available'));
            $this->routeHelper->goAndExit('/dashboard');
        }
    }
}
