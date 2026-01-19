<?php

namespace Foodsharing\Modules\Mails;

use Foodsharing\Lib\Db\Mem;
use Foodsharing\Utility\ConsoleHelper;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

class OutgoingMailsService
{
    public function __construct(
        private readonly MailsGateway $mailsGateway,
        private readonly MailerInterface $mailer,
        private readonly Mem $mem,
    ) {
        error_reporting(E_ALL);
        ini_set('display_errors', '1');
    }

    /**
     * Entry point for the cron job which takes emails from the Redis queue and sends them.
     */
    public function queueWorker(): void
    {
        $this->mem->ensureConnected();
        $running = true;
        while ($running) {
            $elem = $this->mem->cache->brpoplpush('workqueue', 'workqueueprocessing', 10);
            if ($elem !== false && $e = unserialize($elem)) {
                if ($e['type'] == 'email') {
                    $res = $this->handleEmailRateLimited($e['data']);
                } else {
                    $res = false;
                }

                if ($res) {
                    $this->mem->cache->lrem('workqueueprocessing', $elem, 1);
                } else {
                    sleep(3);
                    /* trigger a restart as there is the database and SMTP connection that can hang :-( */
                    $running = false;
                    // TODO handle failed tasks?
                }
            }
        }
    }

    /**
     * Prepares and sends one email.
     *
     * @param array $data the email
     * @return bool if the email was processed and should be removed from the queue
     */
    private function handleEmailRateLimited(array $data): bool
    {
        ConsoleHelper::info('Mail from: ' . $data['from'][0] . ' (' . $data['from'][1] . ')');
        $email = new Email();

        $mailParts = explode('@', (string)$data['from'][0]);
        $fromDomain = end($mailParts);

        if (in_array($fromDomain, MAILBOX_OWN_DOMAINS, true)) {
            $email->from(new Address($data['from'][0], $data['from'][1] ?? ''));
        } else {
            $email->from(new Address(DEFAULT_EMAIL, $data['from'][1] ?? ''));
            $email->replyTo(new Address($data['from'][0], $data['from'][1] ?? ''));
        }

        $subject = preg_replace('/\s+/', ' ', trim((string)$data['subject']));
        if (!$subject) {
            $subject = '[Leerer Betreff]';
        }
        $email->subject($subject);
        $email->html($data['html']);
        $email->text($data['body']);

        if (!empty($data['attachments'])) {
            foreach ($data['attachments'] as $a) {
                $email->attachFromPath($a[0], $a[1]);
            }
        }
        $mailCount = 0;
        $recipients = [];
        foreach ($data['recipients'] as $r) {
            $r[0] = strtolower((string)$r[0]);
            ConsoleHelper::info('To: ' . $r[0]);
            $address = explode('@', $r[0]);
            if (count($address) != 2) {
                ConsoleHelper::error('invalid address');
                continue;
            }
            if (!$this->mailsGateway->emailIsBouncing($r[0])) {
                if (!empty($r[1])) {
                    $recipients[] = new Address($r[0], $r[1]);
                } else {
                    $recipients[] = new Address($r[0]);
                }
                ++$mailCount;
            } else {
                ConsoleHelper::error('bouncing address');
            }
        }
        $email->to(...$recipients);
        if ($mailCount < 1) {
            return true;
        }

        for ($attemptsLeft = 2; $attemptsLeft > 0; --$attemptsLeft) {
            ConsoleHelper::info('send email tries remaining ' . $attemptsLeft);
            try {
                $this->mailer->send($email);
                ConsoleHelper::success('email send OK');

                // rate limiting
                usleep($mailCount * DELAY_MICRO_SECONDS_BETWEEN_MAILS);

                return true;
            } catch (\Throwable $e) {
                ConsoleHelper::error('email send error: ' . $e->getMessage());
                ConsoleHelper::error(print_r($data, true));
            }
        }

        // no attempts left
        return false;
    }
}
