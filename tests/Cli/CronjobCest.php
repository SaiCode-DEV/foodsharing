<?php

declare(strict_types=1);

namespace Tests\Cli;

use Foodsharing\Modules\Core\DBConstants\Voting\VotingNotificationType;
use Tests\Support\CliTester;

class CronjobCest
{
    private array $region;
    private array $userFoodsaver;
    private array $polls;

    public function _before(CliTester $I): void
    {
        $this->region = $I->createRegion();
        $this->userFoodsaver = $I->createFoodsaver(null, ['bezirk_id' => $this->region['id']]);
        $this->createTestPolls($I);
    }

    /**
     * Creates a set of polls for cronjob testing.
     */
    private function createTestPolls(CliTester $I): void
    {
        $now = new \DateTimeImmutable();
        $regionId = $this->region['id'];
        $userId = $this->userFoodsaver['id'];

        // Completely in the past
        // -> we don't expect notifications for this one
        $pastPoll = $I->createPoll(
            $regionId,
            $userId,
            [
                'name' => 'Past Poll',
                'start' => $now->modify('-5 days')->format('Y-m-d H:i:s'),
                'end' => $now->modify('-3 days')->format('Y-m-d H:i:s'),
                'notifications_sent' => VotingNotificationType::BOTH_SENT,
            ]
        );

        // Started in the past, ending soon (within 24h)
        // -> we expect an ending soon notification for this one
        $endingSoonPoll = $I->createPoll(
            $regionId,
            $userId,
            [
                'name' => 'Ending Soon',
                'start' => $now->modify('-2 days')->format('Y-m-d H:i:s'),
                'end' => $now->modify('+20 hours')->format('Y-m-d H:i:s'),
                'notifications_sent' => VotingNotificationType::START_SENT,
            ]
        );

        // Started just now, ends in < 30 hours
        // -> we expect a started but no ending notification for this one
        $shortPoll = $I->createPoll(
            $regionId,
            $userId,
            [
                'name' => 'Short Poll',
                'start' => $now->format('Y-m-d H:i:s'),
                'end' => $now->modify('+20 hours')->format('Y-m-d H:i:s'),
                'notifications_sent' => VotingNotificationType::NONE,
            ]
        );

        // Started recently, ends in a few days
        // -> we expect a started notification for this one
        $longPoll = $I->createPoll(
            $regionId,
            $userId,
            [
                'name' => 'Long Poll',
                'start' => $now->modify('-1 hour')->format('Y-m-d H:i:s'),
                'end' => $now->modify('+3 days')->format('Y-m-d H:i:s'),
                'notifications_sent' => VotingNotificationType::NONE,
            ]
        );

        // Future poll (not started)
        // -> we don't expect notifications for this one
        $futurePoll = $I->createPoll(
            $regionId,
            $userId,
            [
                'name' => 'Future Poll',
                'start' => $now->modify('+2 days')->format('Y-m-d H:i:s'),
                'end' => $now->modify('+4 days')->format('Y-m-d H:i:s'),
                'notifications_sent' => VotingNotificationType::NONE,
            ]
        );

        $this->polls = [
            'past' => $pastPoll,
            'endingSoon' => $endingSoonPoll,
            'short' => $shortPoll,
            'long' => $longPoll,
            'future' => $futurePoll,
        ];
    }

    public function cronjobRunsAndDoesMaintenanceJobs(CliTester $I)
    {
        $I->am('Cron');
        $I->wantTo('see that maintenance jobs do execute');
        $I->amInPath('');
        $I->runShellCommand('bin/console foodsharing:cronjob');
        $I->seeResultCodeIs(0);

        // Check for expected output fragments
        // "Short Poll" + "Long Poll" -> 2 started polls
        $I->seeInShellOutput('Sent notifications for 2 started polls');
        // "Ending Soon" -> 1 ending poll
        $I->seeInShellOutput('Sent notifications for 1 ending polls');
    }
}
