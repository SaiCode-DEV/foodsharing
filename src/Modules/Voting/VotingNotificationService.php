<?php

namespace Foodsharing\Modules\Voting;

use Foodsharing\Modules\Bell\BellGateway;
use Foodsharing\Modules\Bell\DTO\Bell;
use Foodsharing\Modules\Core\DBConstants\Bell\BellType;
use Foodsharing\Modules\Region\RegionGateway;
use Foodsharing\Utility\ConsoleHelper;

class VotingNotificationService
{
    /**
     * Minimum duration (in hours) for sending poll ending notifications.
     */
    private const MIN_HOURS_FOR_END_NOTIFICATION = 30;

    private readonly VotingGateway $votingGateway;
    private readonly BellGateway $bellGateway;
    private readonly RegionGateway $regionGateway;

    public function __construct(
        VotingGateway $votingGateway,
        BellGateway $bellGateway,
        RegionGateway $regionGateway
    ) {
        $this->votingGateway = $votingGateway;
        $this->bellGateway = $bellGateway;
        $this->regionGateway = $regionGateway;
    }

    /**
     * Sends notifications for polls that have started but haven't been notified yet.
     *
     * This will check all polls that have started and haven't had a notification sent,
     * send notifications, and mark them as notified.
     */
    public function notifyForStartedPolls(): void
    {
        // Fetch polls that have started but haven't been notified yet
        $startedPolls = $this->votingGateway->getStartedPolls();

        foreach ($startedPolls as $poll) {
            $region = $this->regionGateway->getRegion($poll->regionId);

            // Get all eligible voters for the poll
            $voters = $this->votingGateway->getEligibleVotersForPoll($poll->id);

            if (!empty($voters)) {
                $bellData = Bell::create(
                    'poll_started_title',
                    'poll_started',
                    'fas fa-poll-h',
                    ['href' => '/poll?id=' . $poll->id],
                    ['title' => $poll->name, 'region' => $region['name']],
                    BellType::createIdentifier(BellType::POLL_STARTED, $poll->id)
                );

                $this->bellGateway->addBellForUsers($voters, $bellData);
            }

            // Mark this poll as having been notified
            $this->votingGateway->markStartNotificationSent($poll->id);
        }

        // Log the number of notifications sent
        ConsoleHelper::info('Sent notifications for ' . count($startedPolls) . ' started polls');
    }

    /**
     * Sends notifications for polls that are ending soon and users haven't voted yet.
     *
     * Checks polls ending in the next 24 hours that haven't been notified yet
     * (but more than x hours total duration) and sends reminders to users who haven't voted.
     */
    public function notifyForEndingPolls(): void
    {
        // Fetch polls ending soon that haven't had end notifications sent
        $endingPolls = $this->votingGateway->getEndingSoonPolls();

        $notificationsSent = 0;
        foreach ($endingPolls as $poll) {
            // Only send notifications for polls that last more than the minimum hours total
            $pollDuration = $poll->startDate->diff($poll->endDate);
            $totalHours = $pollDuration->days * 24 + $pollDuration->h;

            if ($totalHours < self::MIN_HOURS_FOR_END_NOTIFICATION) {
                // Mark as notified without sending - poll is too short
                $this->votingGateway->markEndNotificationSent($poll->id);
                continue;
            }

            $region = $this->regionGateway->getRegion($poll->regionId);

            // Get users who haven't voted yet
            $nonVoters = $this->votingGateway->getNonVotersForPoll($poll->id);

            if (!empty($nonVoters)) {
                $bellData = Bell::create(
                    'poll_ending_soon_title',
                    'poll_ending_soon',
                    'fas fa-clock',
                    ['href' => '/poll?id=' . $poll->id],
                    ['title' => $poll->name, 'region' => $region['name']],
                    BellType::createIdentifier(BellType::POLL_ENDING_SOON, $poll->id)
                );

                $this->bellGateway->addBellForUsers($nonVoters, $bellData);
            }

            // Mark this poll as having been notified, even if there are no non-voters
            $this->votingGateway->markEndNotificationSent($poll->id);
            ++$notificationsSent;
        }

        // Log the number of notifications sent
        ConsoleHelper::info('Sent notifications for ' . $notificationsSent . ' ending polls');
    }
}
