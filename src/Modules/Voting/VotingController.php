<?php

namespace Foodsharing\Modules\Voting;

use Exception;
use Foodsharing\Lib\FoodsharingController;
use Foodsharing\Modules\Core\DBConstants\Unit\UnitType;
use Foodsharing\Modules\Region\RegionController;
use Foodsharing\Modules\Region\RegionGateway;
use Foodsharing\Permissions\VotingPermissions;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class VotingController extends FoodsharingController
{
    public function __construct(
        private readonly VotingGateway $votingGateway,
        private readonly VotingPermissions $votingPermissions,
        private readonly VotingTransactions $votingTransactions,
        private readonly RegionGateway $regionGateway,
        private readonly RegionController $regionController,
    ) {
        parent::__construct();
    }

    #[Route('/poll', name: 'poll')]
    public function index(Request $request): Response
    {
        try {
            $id = $request->query->get('id');
            $sub = $request->query->get('sub');
            $bid = $request->query->get('bid');

            if (isset($id) && ($poll = $this->votingTransactions->getPoll($id, true))) {
                if (!$this->votingPermissions->maySeePoll($poll)) {
                    return $this->regionController->missingMembershipRedirect($poll->regionId);
                }
                $region = $this->regionGateway->getRegion($poll->regionId);
                $this->pageHelper->addBread($region['name'], '/region?bid=' . $region['id']);
                $this->pageHelper->addBread($this->translator->trans('terminology.polls'), '/region?bid=' . $region['id'] . '&sub=polls');
                $this->pageHelper->addBread($poll->name);
                $this->pageHelper->addTitle($poll->name);

                if (isset($sub) && $sub === 'edit') {
                    if ($this->votingPermissions->mayEditPoll($poll)) {
                        $this->pageHelper->addContent($this->prepareVueComponent('new-poll-form', 'NewPollForm', [
                            'poll' => $poll,
                        ]));
                    } else {
                        $this->flashMessageHelper->error($this->translator->trans('poll.may_not_edit'));

                        return $this->redirect('/poll?id=' . $poll->id);
                    }
                } else {
                    $mayVote = $this->votingPermissions->mayVote($poll);
                    try {
                        $voteDateTime = $this->votingGateway->getVoteDatetime($poll->id, $this->session->id());
                    } catch (Exception) {
                        $voteDateTime = null;
                    }
                    $mayEdit = $this->votingPermissions->mayEditPoll($poll);
                    $this->pageHelper->addContent($this->prepareVueComponent('poll-overview', 'PollOverview', [
                        'poll' => $poll,
                        'regionId' => $region['id'],
                        'regionName' => $region['name'],
                        'isWorkGroup' => UnitType::isGroup($region['type']),
                        'mayVote' => $mayVote,
                        'userVoteDate' => $mayVote ? null : $voteDateTime,
                        'mayEdit' => $mayEdit
                    ]));
                }
            } elseif (isset($sub) && $sub === 'new' && isset($bid) && ($region = $this->regionGateway->getRegion($bid))
                && $this->votingPermissions->mayCreatePoll($region['id'], $region['type'])) {
                $this->pageHelper->addBread($region['name'], '/region?bid=' . $region['id']);
                $this->pageHelper->addBread($this->translator->trans('terminology.polls'), '/region?bid=' . $region['id'] . '&sub=polls');
                $this->pageHelper->addBread($this->translator->trans('polls.new_poll'));
                $this->pageHelper->addTitle($this->translator->trans('polls.new_poll'));

                $usersPerScope = $this->votingTransactions->getScopeCounts($region['id'], UnitType::isGroup($region['type']));

                $this->pageHelper->addContent($this->prepareVueComponent('new-poll-form', 'NewPollForm', [
                    'region' => $region,
                    'isWorkGroup' => UnitType::isGroup($region['type']),
                    'usersPerScope' => $usersPerScope,
                ]));
            } else {
                $this->flashMessageHelper->info($this->translator->trans('poll.not_available'));

                return $this->redirectToRoute('dashboard');
            }
        } catch (Exception) {
            $this->flashMessageHelper->info($this->translator->trans('poll.not_available'));

            return $this->redirectToRoute('dashboard');
        }

        return $this->renderGlobal();
    }
}
