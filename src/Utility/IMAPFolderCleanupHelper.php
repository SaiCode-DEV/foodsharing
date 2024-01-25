<?php

namespace Foodsharing\Utility;

use DateInterval;
use DateTimeImmutable;
use Ddeboer\Imap\Search\Date\Before as SearchDateBefore;
use Ddeboer\Imap\Server;
use Foodsharing\Modules\Console\ConsoleControl;

use function Sentry\captureException;

class IMAPFolderCleanupHelper
{
    public function cleanupFolder(?string $imapHost, ?string $imapUser, ?string $imapPass, ?string $folder, ?int $deleteDelayDays): int
    {
        if ($imapHost === null || $imapUser === null || $imapPass === null || $folder === null || $deleteDelayDays === null) {
            ConsoleControl::error('Invalid parameters: All parameters must be provided.');

            return -1;
        }

        $deleted = 0;
        try {
            $server = new Server($imapHost);
            $connection = $server->authenticate($imapUser, $imapPass);

            $mailbox = $connection->getMailbox($folder);
        } catch (\Throwable $e) {
            ConsoleControl::error('Something went wrong connecting to folder "' . $folder . '", ' . $e->getMessage() . '\n');

            return -1;
        }

        try {
            $today = new DateTimeImmutable();
            $toDeleteDaysAgo = $today->sub(new DateInterval('P' . $deleteDelayDays . 'D'));

            $emails = $mailbox->getMessages(
                new SearchDateBefore($toDeleteDaysAgo),
                \SORTDATE,
                true
            );

            foreach ($emails as $email_id) {
                $email_id->delete();
                ++$deleted;
            }
        } catch (\Throwable $e) {
            ConsoleControl::error('Something went wrong removing old mails from "' . $folder . '", ' . $e->getMessage() . '\n');
            captureException($e);
        } finally {
            $connection->expunge();
        }

        return $deleted;
    }
}
