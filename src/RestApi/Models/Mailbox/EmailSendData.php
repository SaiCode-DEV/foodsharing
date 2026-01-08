<?php

namespace Foodsharing\RestApi\Models\Mailbox;

use Ddeboer\Imap\Message\EmailAddress;
use Foodsharing\Modules\Mailbox\Email;
use Foodsharing\Modules\Mailbox\EmailAttachment;
use Nelmio\ApiDocBundle\Annotation\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Contains all the information that the client needs to send to the API in order to send an email. The object can then
 * be converted into an Email object. The remaining data for the Email object must be filled in by the backend.
 */
class EmailSendData
{
    /**
     * A list of recipients. Should never be empty.
     *
     * @var string[]
     */
    #[Assert\Count(min: 1)]
    #[Assert\All([new Assert\Email()])]
    public array $to = [];

    /**
     * An optional list of CC addresses. Can be empty or null.
     *
     * @var string[]
     */
    #[Assert\All([new Assert\Email()])]
    public ?array $cc = null;

    /**
     * An optional list of BCC addresses. Can be empty or null.
     *
     * @var string[]
     */
    #[Assert\All([new Assert\Email()])]
    public ?array $bcc = null;

    /**
     * Subject of the email. Can be empty but not null.
     */
    #[OA\Property(example: 'Testbetreff')]
    #[Assert\Length(max: 65535)]
    public string $subject = '';

    /**
     * Body of this email.
     */
    #[OA\Property(example: 'Inhalt der Email')]
    public ?string $body = null;

    /**
     * @var EmailSendAttachment[]|null List of attachments
     */
    #[OA\Property(
        title: 'Optional list of previously uploaded files that will be used as attachments. Can be empty or null.',
        type: 'array',
        items: new OA\Items(ref: new Model(type: EmailSendAttachment::class))
    )]
    #[Assert\Count(max: 10)]
    public ?array $attachments = null;

    /**
     * Id of the email to which this email is an answer. The original email will be marked as answered.
     */
    public ?int $replyEmailId = null;

    /**
     * Returns an Email object with all the information that this object contains. The remaining fields in the Email
     * object will be set to default values.
     */
    public function toEmail(): Email
    {
        $e = new Email();
        $e->to = array_map(fn ($x) => self::stringToEmailAddress($x), $this->to);
        $e->cc = $this->cc ? array_map(fn ($x) => self::stringToEmailAddress($x), $this->cc) : null;
        $e->bcc = $this->cc ? array_map(fn ($x) => self::stringToEmailAddress($x), $this->bcc) : null;
        $e->subject = $this->subject;
        $e->body = $this->body;
        $e->attachments = $this->attachments ? array_map(fn ($a) => EmailAttachment::create($a->filename, $a->uuid, -1, ''), $this->attachments) : null;

        return $e;
    }

    /**
     * Parses an email address string into an EmailAddress object.
     */
    private static function stringToEmailAddress(string $addressString): EmailAddress
    {
        $mailParts = explode('@', $addressString);

        return new EmailAddress($mailParts[0], $mailParts[1]);
    }
}
