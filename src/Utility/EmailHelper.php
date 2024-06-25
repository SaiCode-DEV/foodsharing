<?php

namespace Foodsharing\Utility;

use Flourish\fFile;
use Foodsharing\Lib\Db\Mem;
use Foodsharing\Lib\Mail\AsyncMail;
use Twig\Environment;

final class EmailHelper
{
    private $mem;
    private $sanitizerService;
    private $twig;

    public function __construct(
        Mem $mem,
        Sanitizer $sanitizerService,
        Environment $twig
    ) {
        $this->mem = $mem;
        $this->sanitizerService = $sanitizerService;
        $this->twig = $twig;
    }

    private function emailBodyTpl(string $message, $email = false, $token = false, bool $renderUnsubscribe = true): string
    {
        if ($renderUnsubscribe && $email !== false && $token !== false) {
            $unsubscribe = $this->twig->render('emailTemplates/general/unsubscribe_newsletter.html.twig', ['TOKEN' => $token, 'EMAIL' => $email]);
        } elseif ($renderUnsubscribe) {
            $unsubscribe = $this->twig->render('emailTemplates/general/unsubscribe.html.twig', []);
        } else {
            $unsubscribe = '';
        }

        $message = preg_replace('/(<[^>]+) style=".*?"/i', '$1', $message);

        $search = ['<a', '<td', '<li'];
        $replace = ['<a style="color:var(--fs-color-secondary-500)"', '<td style="font-size:13px;font-family:Arial;color:var(--fs-color-warning-800);"', '<li style="margin-bottom:11px"'];
        $message = str_replace($search, $replace, $message);

        return $this->twig->render('emailTemplates/general/body.html.twig', ['MESSAGE' => $message, 'UNSUBSCRIBE' => $unsubscribe]);
    }

    public function tplMail($tpl_id, $to, $var = [], $from_email = false, bool $highPriority = false, bool $renderUnsubscribe = true)
    {
        $mail = new AsyncMail($this->mem);

        if ($from_email !== false && $this->validEmail($from_email)) {
            $mail->setFrom($from_email);
        } else {
            $emailName = '';
            if (array_key_exists('sender', $var)) {
                $emailName = $var['sender'];
            } elseif (array_key_exists('poster', $var)) {
                $emailName = $var['poster'];
            }
            if (array_key_exists('bezirk', $var)) {
                $emailName .= ' in ' . $var['bezirk'];
            }
            if ($emailName !== '') {   // if sender information is present
                $emailName .= ' via '; // though this is optional...
            }
            $emailName .= strtolower((string)DEFAULT_EMAIL_NAME);
            $mail->setFrom(DEFAULT_EMAIL, $emailName);
        }

        $locale = 'de-de';
        $tpl_prefix = 'emailTemplates/' . $tpl_id . '.' . $locale;
        $var = array_change_key_case($var, CASE_UPPER);
        $message = [
            'subject' => $this->twig->render($tpl_prefix . '.subject.twig', $var),
            'body' => $this->twig->render($tpl_prefix . '.body.html.twig', $var)
        ];

        $htmlBody = $this->emailBodyTpl($message['body'], false, false, $renderUnsubscribe);
        $mail->setHTMLBody($htmlBody);

        // playintext body
        $plainBody = $this->sanitizerService->htmlToPlain($htmlBody);
        $mail->setBody($plainBody);

        if (!$message['subject']) {
            $message['subject'] = 'foodsharing-Mail: {EXCERPT}';
        }

        if (mb_strpos($message['subject'], '{EXCERPT}') !== false) {
            $plainMessage = $this->sanitizerService->htmlToPlain($message['body']);
            $subjectLength = mb_strlen($message['subject']) - strlen('{EXCERPT}');
            /* RFC recommends 78 characters for subjects */
            $excerpt = $this->sanitizerService->tt($plainMessage, 78 - $subjectLength);
            $message['subject'] = str_replace('{EXCERPT}', $excerpt, $message['subject']);
        }

        $mail->setSubject($message['subject']);

        if (is_iterable($to)) {
            foreach ($to as $recipient) {
                $mail->addRecipient($recipient);
            }
        } else {
            $mail->addRecipient($to);
        }
        $mail->setHighPriority($highPriority);
        $mail->send();
    }

    public function validEmail(string $email): bool
    {
        if (filter_var($email, FILTER_VALIDATE_EMAIL, FILTER_FLAG_EMAIL_UNICODE)) {
            return true;
        }

        return false;
    }

    public function isFoodsharingEmailAddress(string $email): bool
    {
        $mailParts = explode('@', $email);
        $domain = end($mailParts);

        return in_array($domain, MAILBOX_OWN_DOMAINS, true);
    }

    public function libmail($bezirk, $email, $subject, $message, $attach = false, $token = false,
        bool $highPriority = false): bool
    {
        if ($bezirk === false) {
            $bezirk = [
                'email' => DEFAULT_EMAIL,
                'email_name' => DEFAULT_EMAIL_NAME
            ];
        } elseif (!is_array($bezirk)) {
            $bezirk = [
                'email' => $bezirk,
                'email_name' => $bezirk
            ];
        } else {
            if (!$this->validEmail($bezirk['email'])) {
                $bezirk['email'] = EMAIL_PUBLIC;
            }
            if (empty($bezirk['email_name'])) {
                $bezirk['email_name'] = EMAIL_PUBLIC_NAME;
            }
        }

        if (!$this->validEmail($email)) {
            return false;
        }

        $mail = new AsyncMail($this->mem);
        $mail->setFrom($bezirk['email'], $bezirk['email_name']);
        $mail->addRecipient($email);
        if (!$subject) {
            $subject = 'foodsharing-Mail';
        }
        $mail->setSubject($subject);
        $htmlBody = $this->emailBodyTpl($message, $email, $token);
        $mail->setHTMLBody($htmlBody);

        $plainBody = $this->sanitizerService->htmlToPlain($htmlBody);
        $mail->setBody($plainBody);

        if ($attach !== false) {
            foreach ($attach as $a) {
                $mail->addAttachment(new fFile($a['path']), $a['name']);
            }
        }

        $mail->setHighPriority($highPriority);
        $mail->send();

        return true;
    }
}
