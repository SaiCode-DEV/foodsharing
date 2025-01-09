<?php

namespace Foodsharing\Modules\Mailbox;

use Carbon\Carbon;
use Ddeboer\Imap\Message\EmailAddress;
use Foodsharing\Lib\Db\Mem;
use Foodsharing\Lib\Mail\AsyncMail;
use Foodsharing\Lib\Session;
use Foodsharing\Modules\Core\DBConstants\Mailbox\MailboxFolder;
use Foodsharing\Modules\Core\DBConstants\Uploads\UploadUsage;
use Foodsharing\Modules\Uploads\UploadsGateway;
use Foodsharing\Modules\Uploads\UploadsTransactions;
use Foodsharing\Permissions\MailboxPermissions;
use Foodsharing\Permissions\UploadsPermissions;
use Foodsharing\RestApi\Models\Mailbox\EmailSendData;
use Foodsharing\Utility\EmailHelper;
use Foodsharing\Utility\Sanitizer;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Contracts\Translation\TranslatorInterface;

class MailboxTransactions
{
    private const int MAX_VALID_RECIPIENTS = 100;
    private const string OLD_EMAIL_ATTACHMENT_DIRECTORY = 'data/mailattach/';

    public function __construct(
        private readonly MailboxGateway $mailboxGateway,
        private readonly MailboxPermissions $mailboxPermissions,
        private readonly UploadsTransactions $uploadsTransactions,
        private readonly UploadsGateway $uploadsGateway,
        private readonly EmailHelper $emailHelper,
        private readonly Session $session,
        private readonly Mem $mem,
        private readonly Sanitizer $sanitizer,
        private readonly TranslatorInterface $translator,
        private readonly UploadsPermissions $uploadsPermissions,
    ) {
    }

    /**
     * Lists all emails in a folder of a mailbox. This also updates the last access timestamp of the mailbox.
     *
     * @return Email[]
     */
    public function listEmails(int $mailboxId, int $folder, int $page, int $pageSize): array
    {
        $this->mailboxGateway->updateMailboxActivityIndicator($mailboxId);

        return $this->mailboxGateway->listEmails($mailboxId, $folder, $page, $pageSize);
    }

    /**
     * Fetches an email from the database and makes sure that the meta data for all attachments is correct.
     * After this the email will always be marked as read.
     */
    public function getEmail(int $emailId): Email
    {
        $this->mailboxGateway->markEmailAsRead($emailId, true);

        $email = $this->mailboxGateway->getEmail($emailId);

        // obtain the file sizes for all attachments
        if (!empty($email->attachments)) {
            foreach ($email->attachments as $attachment) {
                $path = $this->getAttachmentFilePath($attachment);
                $attachment->size = file_exists($path) ? filesize($path) : EmailAttachment::SIZE_UNKNOWN;

                // Mark old files with a prefix so that they can be distinguished in the frontend
                if (str_starts_with($path, self::OLD_EMAIL_ATTACHMENT_DIRECTORY)) {
                    $attachment->hashedFileName = 'old:' . $attachment->hashedFileName;
                }
            }
        }

        return $email;
    }

    /**
     * Converts the email data into an actuall Email object, pushes it into the Redis queue for sending, and saves it
     * in the 'sent' folder. If this email is an answer to another one, the other email will be marked as answered.
     * As sending is done asynchronously in the Mails module, this function does not know if sending is successful.
     *
     * @param EmailSendData $emailData content of the email from the client
     * @param int $mailboxId id of the mailbox from which the email will be sent
     *
     * @return Email the complete email object
     */
    public function sendAndSaveEmail(EmailSendData $emailData, int $mailboxId): Email
    {
        $email = $this->completeEmailForSending($emailData, $mailboxId);
        $this->sendMail($email);
        $email->id = $this->saveMail($email, $emailData->replyEmailId);

        return $email;
    }

    /**
     * When sending an email, the client only all the data that is necessary for an EmailSendData object. This function
     * converts into an Email object and fills the remaining fields.
     *
     * @param EmailSendData $emailData the data that was send from the client
     * @param int $mailboxId id of the mailbox from which this email is going to be sent
     *
     * @return Email the completed email object
     */
    private function completeEmailForSending(EmailSendData $emailData, int $mailboxId): Email
    {
        $email = $emailData->toEmail();
        $email->mailboxId = $mailboxId;
        $email->mailboxFolder = MailboxFolder::FOLDER_SENT;

        $mailbox = $this->mailboxGateway->getMailbox($mailboxId);
        $email->from = new EmailAddress($mailbox['name'], PLATFORM_MAILBOX_HOST, $mailbox['email_name']);

        $email->time = Carbon::now();
        $email->isRead = true;
        $email->isAnswered = false;

        // fetch the previously uploaded attachments from the database
        if (!is_null($email->attachments)) {
            foreach ($email->attachments as $attachment) {
                $upload = $this->uploadsTransactions->getUploadedFile($attachment->hashedFileName);
                if (is_null($upload)) {
                    throw new NotFoundHttpException();
                } elseif (!$this->uploadsPermissions->mayUseUploadAsEmailAttachment($upload)) {
                    throw new AccessDeniedHttpException();
                }
                $attachment->size = $upload->fileSize;
                $attachment->mimeType = $upload->mimeType;
            }
        }

        return $email;
    }

    private function sendMail(Email $email): void
    {
        $mail = new AsyncMail($this->mem);

        $mail->setFrom($email->from->getAddress(), $email->from->getName());

        foreach ($email->to as $e) {
            if ($this->emailHelper->validEmail($e->getFullAddress())) {
                $this->mailboxGateway->addContact($e->getFullAddress(), $this->session->id());
                $mail->addRecipient($e->getAddress(), $e->getName());
            }
        }

        $mail->setSubject($email->subject);

        $message = str_replace(['<br>', '<br/>', '<br />', '<p>', '</p>', '</p>'], "\r\n", $email->body);
        $message = strip_tags($message);

        $html = nl2br($message);
        $mail->setHTMLBody($html);

        $plainBody = $this->sanitizer->htmlToPlain($html);
        $mail->setBody($plainBody);

        if ($email->attachments) {
            foreach ($email->attachments as $a) {
                $path = $this->uploadsTransactions->generateFilePath($a->hashedFileName);
                $mail->addAttachment($path, $a->fileName);
            }
        }

        $mail->send();
    }

    /**
     * Filters out duplicates from the list of email addresses and checks if the number of recipients passes the limit.
     *
     * @param string[] $addresses a list of email addresses
     *
     * @return string[] a list of unique and valid email addresses
     */
    public function validateRecipients(array $addresses): array
    {
        $trimmed = array_map(fn ($address) => trim($address), $addresses);

        $unique = array_unique($trimmed);

        if (count($unique) > self::MAX_VALID_RECIPIENTS) {
            throw new BadRequestHttpException($this->translator->trans('mailbox.recipients'));
        }

        return $unique;
    }

    /**
     * Saves an email in the database and returns the email's new id. If this email is an answer to another one, the
     * other email will be marked as answered.
     *
     * @param Email $email the email to save
     * @param int|null $replyEmailId optional id of an email to which the email is an answer
     *
     * @return int the email's id
     */
    private function saveMail(Email $email, ?int $replyEmailId): int
    {
        $savedMessageId = $this->mailboxGateway->saveMessage($email);
        if ($savedMessageId && $replyEmailId) {
            if ($this->mailboxPermissions->mayMailbox($this->mailboxGateway->getMailboxId($replyEmailId))) {
                $this->mailboxGateway->setAnswered($replyEmailId);
            }
        }

        // Now that the email has an id, update the usage value of all attachments
        if (!is_null($email->attachments)) {
            $this->uploadsGateway->setUsage(
                array_column($email->attachments, 'hashedFileName'),
                UploadUsage::EMAIL_ATTACHMENT,
                $savedMessageId
            );
        }

        return $savedMessageId;
    }

    /**
     * Returns the path to the attached file. This distinguishes files uploaded before and after switching to the new
     * upload API.
     */
    private function getAttachmentFilePath(EmailAttachment $attachment): string
    {
        $fileOld = self::OLD_EMAIL_ATTACHMENT_DIRECTORY . $attachment->hashedFileName;
        if (file_exists($fileOld)) {
            return $fileOld;
        } else {
            return $this->uploadsTransactions->generateFilePath($attachment->hashedFileName);
        }
    }

    /**
     * Deletes the email with the specified id and all of its attachments.
     */
    public function deleteEmail(int $emailId): void
    {
        $email = $this->mailboxGateway->getEmail($emailId);
        if (!is_null($email->attachments)) {
            foreach ($email->attachments as $attachment) {
                if (file_exists(self::OLD_EMAIL_ATTACHMENT_DIRECTORY . $attachment->hashedFileName)) {
                    // old email attachments
                    unlink(self::OLD_EMAIL_ATTACHMENT_DIRECTORY . $attachment->hashedFileName);
                } else {
                    // new upload API
                    $this->uploadsTransactions->deleteUploadedFile($attachment->hashedFileName);
                }
            }
        }

        $this->mailboxGateway->deleteMessage($emailId);
    }
}
