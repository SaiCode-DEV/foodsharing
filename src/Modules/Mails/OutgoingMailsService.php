<?php

namespace Foodsharing\Modules\Mails;

use Foodsharing\Lib\Db\Mem;
use Foodsharing\Utility\ConsoleHelper;
use RuntimeException;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Throwable;

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
        $maxAttempts = 3;
        while ($running) {
            // Try to retrieve one element from the queue
            try {
                $rawElement = $this->mem->cache->brPop('workqueue', 10);
            } catch (Throwable $ex) {
                ConsoleHelper::error('Redis brpoplpush failed: ' . $ex->getMessage());
                sleep(5);
                $this->mem->ensureConnected();
                continue;
            }
            if (is_array($rawElement)) {
                $rawElement = $rawElement[1] ?? false;
            }
            if ($rawElement === false) {
                continue; // timeout, loop again
            }

            // Unserialise it and make sure that it is a valid element
            $element = @unserialize($rawElement);
            if (!is_array($element) || !isset($element['data'])) {
                ConsoleHelper::error('Invalid queue payload, moving to workqueue:failed');
                try {
                    $this->mem->cache->lpush('workqueue:failed', $rawElement);
                } catch (Throwable $ex) {
                    ConsoleHelper::error('Failed to move invalid payload to failed queue: ' . $ex->getMessage());
                }
                continue;
            }

            // Process it
            $processed = false;
            for ($attempts = 0; $attempts < $maxAttempts && !$processed; ++$attempts) {
                try {
                    $this->handleEmailRateLimited($element['data']);
                    $processed = true;
                } catch (Throwable $ex) {
                    ConsoleHelper::error('Error processing element: ' . $ex->getMessage());
                    sleep(3);
                }
            }

            // If it was not processed successfully, push it into the queue for failed elements
            if (!$processed) {
                ConsoleHelper::info('Email not processed after ' . $attempts . ' attempts');

                try {
                    $this->mem->cache->lpush('workqueue:failed', $rawElement);
                    ConsoleHelper::error('Task failed after ' . $attempts . ' attempts, moved to workqueue:failed');
                } catch (Throwable $ex) {
                    ConsoleHelper::error('Failed to requeue failed task: ' . $ex->getMessage());
                    // If we cannot push to Redis, break to allow the cron job to restart
                    sleep(5);
                    $running = false;
                }
            }
        }
    }

    /**
     * Prepares and sends one email.
     *
     * @param array $data the email
     * @throws RuntimeException if the email was not processed successfully
     */
    private function handleEmailRateLimited(array $data): void
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

        // an explicit reply address wins over the sender-derived one above
        if (!empty($data['replyTo'][0])) {
            $email->replyTo(new Address($data['replyTo'][0], $data['replyTo'][1] ?? ''));
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
            return;
        }

        for ($attemptsLeft = 2; $attemptsLeft > 0; --$attemptsLeft) {
            try {
                $this->mailer->send($email);
                ConsoleHelper::success('email send OK');

                // rate limiting
                usleep($mailCount * DELAY_MICRO_SECONDS_BETWEEN_MAILS);

                return;
            } catch (Throwable $e) {
                ConsoleHelper::error('email send error: ' . $e->getMessage());
                ConsoleHelper::error(print_r($data, true));
                ConsoleHelper::error('tries remaining: ' . $attemptsLeft);
            }
        }

        // no attempts left
        throw new RuntimeException('Failed to send email after 3 attempts');
    }
}
