<?php

namespace Foodsharing\Modules\Mails;

use Ddeboer\Imap\Message\AttachmentInterface;
use Ddeboer\Imap\MessageInterface;
use Ddeboer\Imap\Server;
use Foodsharing\Modules\Core\DBConstants\Mailbox\MailboxFolder;
use Foodsharing\Utility\ConsoleHelper;
use Foodsharing\Utility\EmailHelper;
use Foodsharing\Utility\RouteHelper;
use Foodsharing\Utility\Sanitizer;

use function Sentry\captureException;

class IncomingMailsService
{
    private const string DEFAULT_TIMEZONE = 'Europe/Berlin';

    public function __construct(
        private readonly MailsGateway $mailsGateway,
        private readonly RouteHelper $routeHelper,
        private readonly EmailHelper $emailHelper,
        private readonly Sanitizer $sanitizer,
    ) {
        error_reporting(E_ALL);
        ini_set('display_errors', '1');
    }

    /**
     * Entry point for the cron job which will fetch incoming emails from all mailboxes.
     */
    public function fetchMails(): void
    {
        foreach (IMAP as $imap) {
            $stats = $this->mailboxupdate($imap['host'], $imap['user'], $imap['password']);
        }
    }

    /**
     * This method will check for new e-mails and sort them into the mailboxes.
     */
    private function mailboxupdate($host, $user, $password): IncomingEmailStatistics
    {
        $server = new Server($host);
        $connection = $server->authenticate($user, $password);

        $mailbox = $connection->getMailbox('INBOX');
        if (!$connection->hasMailbox(IMAP_FAILED_BOX)) {
            $connection->createMailbox(IMAP_FAILED_BOX);
        }
        $failedMailbox = $connection->getMailbox(IMAP_FAILED_BOX);

        $messages = $mailbox->getMessages();
        $stats = new IncomingEmailStatistics();

        foreach ($messages as $msg) {
            try {
                $this->handleEmail($msg, $stats);
                $msg->delete(); // message has been processed at this point, mark it for deletion
            } catch (\Exception $e) {
                ConsoleHelper::error('Something went wrong, ' . $e->getMessage() . "\n");
                captureException($e);
                $msg->move($failedMailbox);
            }
        }

        // actually delete all messages that were processed
        $connection->expunge();

        return $stats;
    }

    private function handleEmail(MessageInterface $msg, IncomingEmailStatistics $stats): void
    {
        $mailboxes = [];
        $recipients = array_merge($msg->getTo(), $msg->getCc());

        foreach ($recipients as $to) {
            if (in_array(strtolower($to->getHostname() ?? ''), MAILBOX_OWN_DOMAINS)) {
                $mailboxes[] = $to->getMailbox();
            }
        }

        $mailboxIds = [];
        if (!empty($mailboxes)) {
            $mailboxIds = $this->mailsGateway->getMailboxIds($mailboxes);
        }

        if (empty($mailboxIds)) {
            $this->sendAutoReplyMessage($msg, $mailboxes);
            ++$stats->unknownRecipient;

            return;
        }

        try {
            $html = $msg->getBodyHtml();
        } catch (\Exception $e) {
            $html = null;
            ConsoleHelper::error('Could not get HTML body ' . $e->getMessage() . ', continuing with PLAIN TEXT\n');
        }

        if ($html) {
            $body = $this->sanitizer->htmlToPlain($html);
            $html = $this->sanitizer->purifyHtml($html);
        } else {
            try {
                $text = $msg->getBodyText();
            } catch (\Exception $e) {
                $text = null;
                ConsoleHelper::error('Could not get PLAIN TEXT body ' . $e->getMessage() . ', skipping mail.\n');
            }
            if ($text != null) {
                $body = $text;
                $html = nl2br($this->routeHelper->autolink($text));
            } else {
                $body = '';
                $html = '';
            }
        }

        $attach = [];
        foreach ($msg->getAttachments() as $i => $attachment) {
            $result = $this->saveAttachment($i, $attachment);
            if ($result) {
                $attach[] = $result;
            }
        }
        if ($attach) {
            ++$stats->hasAttachment;
        }
        $attach = json_encode($attach);

        $date = null;
        try {
            $date = $msg->getDate();
        } catch (\Exception $e) {
            ConsoleHelper::error('Error parsing date: ' . $e->getMessage() . ", continuing with 'now'\n");
        }
        if ($date === null) {
            $date = new \DateTime();
        }

        // Convert date to Berlin timezone before saving to database
        $date->setTimezone(new \DateTimeZone(self::DEFAULT_TIMEZONE));

        foreach ($mailboxIds as $id) {
            $from = [
                'mailbox' => $msg->getFrom()->getMailbox(),
                'host' => $msg->getFrom()->getHostname()
            ];
            $name = $msg->getFrom()->getName();
            if ($name) {
                $from['personal'] = $name;
            }

            $this->mailsGateway->saveMessage(
                $id, // mailbox id
                MailboxFolder::FOLDER_INBOX,
                json_encode($from),
                json_encode(array_map(fn ($r) => ['mailbox' => $r->getMailbox(), 'host' => $r->getHostname()], $recipients)),
                $msg->getSubject() ?? '',
                $body,
                $html,
                $date->format('Y-m-d H:i:s'),
                $attach
            );
        }
        ++$stats->delivered;
    }

    /**
     * Attempts to save an attachment to a file.
     *
     * @param int $index the index of the attachment in the email
     * @param AttachmentInterface $attachment the attachment
     * @return ?string[] (filename, origname, mime) or null if the attachment could not be saved
     */
    private function saveAttachment(int $index, AttachmentInterface $attachment): ?array
    {
        $filename = $attachment->getFilename();
        if ($filename === null) {
            $filename = 'unknown_' . $index;
            ConsoleHelper::info('Attachment without(?) a specified filename encountered. gave it a generic one (' . $filename . ')\n');
        }
        if (!$this->isAttachmentAllowed($filename)) {
            return null;
        }

        $new_filename = bin2hex(random_bytes(16));
        $path = 'data/mailattach/';
        $j = 0;
        while (file_exists($path . $new_filename)) {
            ++$j;
            $new_filename = $j . '-' . $filename;
        }
        try {
            file_put_contents($path . $new_filename, $attachment->getDecodedContent());

            return [
                'filename' => $new_filename,
                'origname' => $filename,
                'mime' => mime_content_type($path . $new_filename)
            ];
        } catch (\Exception $e) {
            ConsoleHelper::error('Could not parse/save an attachment (' . $e->getMessage() . "), skipping that one...\n");

            return null;
        }
    }

    private function isAttachmentAllowed(string $filename): bool
    {
        if (strlen($filename) < 300) {
            $ext = explode('.', $filename);
            $ext = end($ext);
            $ext = strtolower($ext);
            $notallowed = ['php', 'html', 'htm', 'php5', 'php4', 'php3', 'php2', 'php1'];

            return !in_array($ext, $notallowed);
        }

        return false;
    }

    /**
     * Sends a reply to the sender of the e-mail that no recipient mailbox could be found.
     *
     * @param MessageInterface $msg the original e-mail
     * @param string[] $mailboxes the mailboxes that do not exist
     */
    private function sendAutoReplyMessage(MessageInterface $msg, array $mailboxes): void
    {
        // send auto-reply message
        $returnPath = $msg->getReturnPath();
        if (!$returnPath) {
            $returnPath = $msg->getFrom();
        } else {
            $returnPath = $returnPath[0];
        }
        if ($returnPath && $returnPath != DEFAULT_EMAIL) {
            $this->emailHelper->tplMail('general/invalid_email_address', $returnPath->getAddress(), ['address' => implode(', ', $mailboxes)], false, true, false);
        }
    }
}
