<?php

namespace Foodsharing\Modules\Mailbox;

use Carbon\Carbon;
use DateTimeZone;
use Ddeboer\Imap\Message\EmailAddress;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Exception;
use Foodsharing\Modules\Core\BaseGateway;
use Foodsharing\Modules\Core\Database;
use Foodsharing\Modules\Core\Pagination;
use Foodsharing\Modules\Mailbox\DTO\Mailbox;
use Foodsharing\Modules\Mailbox\DTO\Region;
use Foodsharing\RestApi\Models\Region\RegionForAdministration;
use Foodsharing\Utility\Sanitizer;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class MailboxGateway extends BaseGateway
{
    private readonly Sanitizer $sanitizer;

    public function __construct(
        Database $db,
        Sanitizer $sanitizer,
    ) {
        parent::__construct($db);
        $this->sanitizer = $sanitizer;
    }

    public function getMailboxname(int $mailbox_id)
    {
        try {
            return $this->db->fetchValueByCriteria('fs_mailbox', 'name', ['id' => $mailbox_id]);
        } catch (Exception) {
            // trigger_error('No mailbox found with id ' . $mailbox_id);
            return false;
        }
    }

    /**
     * Updates the last access date of a mailbox.
     */
    public function updateMailboxActivityIndicator(int $mailboxId): void
    {
        $this->db->update('fs_mailbox', ['last_access' => $this->db->now()], ['id' => $mailboxId]);
    }

    public function addContact(string $email, int $fsId): bool
    {
        try {
            $id = $this->db->fetchValueByCriteria('fs_contact', 'id', ['email' => strip_tags($email)]);
        } catch (Exception) {
            $id = $this->db->insert('fs_contact', ['email' => $email]);
        }

        if ((int)$id > 0) {
            $this->db->insertIgnore('fs_foodsaver_has_contact', ['foodsaver_id' => $fsId, 'contact_id' => (int)$id]);

            return true;
        }

        return false;
    }

    /**
     * Returns the list of all regions with their email addresses for use in the mailbox's autocomplete function.
     *
     * @return Region[]
     */
    public function getRegionsWithMailAdresses(): array
    {
        $regions = $this->db->fetchAll(
            '
			SELECT 	bz.name,
                    bz.id,
                    bz.email_name,
                    bz.parent_id,
                    bz.type,
			        CONCAT(mb.name,"@' . PLATFORM_MAILBOX_HOST . '") as email
			FROM 	fs_mailbox mb,
					fs_bezirk bz
			WHERE 	bz.mailbox_id = mb.id
		'
        );

        return array_map(function ($region) {
            return new Region(
                $region['id'],
                $region['name'],
                $region['parent_id'],
                $region['type'],
                $region['email'],
                $region['email_name']
            );
        }, $regions);
    }

    public function getMailboxesWithUnreadCount(array $mailboxIds): array
    {
        $countMailboxes = count($mailboxIds);
        if ($countMailboxes == 0) {
            return [];
        }

        $placeholders = $this->db->generatePlaceholders(count($mailboxIds));

        return $this->db->fetchAll('
			SELECT	mb.id,
					mb.name,
					(
						SELECT	COUNT(*) FROM fs_mailbox_message mm
						WHERE	mb.id = mm.mailbox_id
						AND		mm.read = 0
					) AS count

			FROM	fs_mailbox mb

			WHERE mb.id IN(' . $placeholders . ');
		', $mailboxIds);
    }

    public function getUnreadMailCount(int $userId): int
    {
        return (int)$this->db->fetchValue(
            'SELECT COUNT(*) AS cnt
             FROM fs_mailbox_message m
             JOIN (
               SELECT mailbox_id FROM fs_bezirk r
                 JOIN fs_botschafter a ON r.id = a.bezirk_id
                 WHERE a.foodsaver_id = :userId AND r.mailbox_id IS NOT NULL
               UNION
               SELECT mailbox_id FROM fs_foodsaver WHERE id = :userId AND mailbox_id IS NOT NULL
               UNION
               SELECT mailbox_id FROM fs_mailbox_member WHERE foodsaver_id = :userId
             ) mb ON m.mailbox_id = mb.mailbox_id
             WHERE m.`read` = 0',
            [':userId' => $userId]
        );
    }

    public function setAnswered(int $message_id): int
    {
        return $this->db->update('fs_mailbox_message', ['answer' => 1], ['id' => $message_id]);
    }

    public function deleteMessage(int $mid): int
    {
        return $this->db->delete('fs_mailbox_message', ['id' => $mid]);
    }

    public function move(int $mail_id, int $folder): int
    {
        return $this->db->update('fs_mailbox_message', ['folder' => $folder], ['id' => $mail_id]);
    }

    public function getEmail(int $emailId): Email
    {
        $data = $this->db->fetch(
            '
			SELECT 	m.`id`,
					m.`folder`,
					m.`sender`,
					m.`to`,
					m.`subject`,
					UNIX_TIMESTAMP(m.`time`) AS time_ts,
					m.`attach`,
					m.`read`,
					m.`answer`,
					m.`body`,
					m.`body_html`,
					m.`mailbox_id`
			FROM 	fs_mailbox_message m
			LEFT JOIN fs_mailbox b
			ON m.mailbox_id = b.id
			WHERE	m.id = :message_id
		',
            [':message_id' => $emailId]
        );

        $data['body'] = $this->sanitizer->purifyHtml($data['body'] ?? '');
        $data['body_html'] = $this->sanitizer->purifyHtml($data['body_html'] ?? '');

        return $this->parseEmail($data);
    }

    public function markEmailAsRead(int $emailId, bool $isRead): void
    {
        $this->db->update('fs_mailbox_message', ['read' => $isRead ? 1 : 0], ['id' => $emailId]);
    }

    /**
     * Returns all emails from a folder of a mailbox without the emails' body.
     *
     * @return Email[]
     */
    public function listEmails(int $mailboxId, int $folder, Pagination $pagination): array
    {
        $query = '
			SELECT 	`id`,
					`folder`,
					`sender`,
					`to`,
					`subject`,
					`time`,
					UNIX_TIMESTAMP(`time`) AS time_ts,
					`attach`,
					`read`,
					`answer`,
			        `mailbox_id`
			FROM 	fs_mailbox_message
			WHERE	mailbox_id = :mailbox_id
			AND 	folder = :farray_folder
			ORDER BY `time` DESC
		' . $this->buildPaginationSqlLimit($pagination);
        $params = $this->addPaginationSqlLimitParameters($pagination, [':mailbox_id' => $mailboxId, ':farray_folder' => $folder]);

        $data = $this->db->fetchAll($query, $params);

        return array_map(fn ($x) => $this->parseEmail($x), $data);
    }

    public function saveMessage(Email $email): int
    {
        $from = $this->formatAddress($email->from);
        $to = $this->formatAddresses($email->to);

        // convert attachments into an array that is stored as json in the database
        $attachments = array_map(fn ($a) => [
            'origname' => $a->fileName,
            'filename' => $a->hashedFileName,
            'mime' => $a->mimeType
        ], $email->attachments ?? []);

        return $this->db->insert(
            'fs_mailbox_message',
            [
                'mailbox_id' => $email->mailboxId,
                'folder' => $email->mailboxFolder,
                'sender' => $from,
                'to' => $to,
                'subject' => strip_tags($email->subject),
                'body' => strip_tags((string)$email->body),
                'body_html' => '',
                'time' => $email->time->format('Y-m-d H:i:s'),
                'attach' => json_encode($attachments),
                'read' => $email->isRead,
                'answer' => $email->isAnswered,
            ]
        );
    }

    public function getMailbox(int $mb_id)
    {
        if ($mb = $this->db->fetchByCriteria('fs_mailbox', ['name'], ['id' => $mb_id])) {
            $mb['email_name'] = '';
            try {
                $mb['email_name'] = $this->db->fetchValue(
                    'SELECT CONCAT(name," ", nachname) FROM fs_foodsaver WHERE mailbox_id = :mb_id',
                    [':mb_id' => $mb_id]
                );

                return $mb;
            } catch (Exception) {
            }

            try {
                $mb['email_name'] = $this->db->fetchValueByCriteria(
                    'fs_bezirk',
                    'email_name',
                    ['mailbox_id' => $mb_id]
                );

                return $mb;
            } catch (Exception) {
            }

            try {
                $mb['email_name'] = $this->db->fetchValue(
                    'SELECT email_name FROM fs_mailbox_member WHERE mailbox_id = :mb_id AND email_name != "" LIMIT 1',
                    [':mb_id' => $mb_id]
                );

                return $mb;
            } catch (Exception) {
            }
        }

        return false;
    }

    /**
     * Returns all mailboxes to which the user has access.
     *
     * @param bool $isAmbassador if the role of the user is at least ambassador
     * @param int|null $fsId the user's id
     * @return Mailbox[]
     * @throws Exception on database error
     */
    public function getBoxes(bool $isAmbassador, ?int $fsId): array
    {
        if ($fsId === null) {
            return [];
        }
        $mailboxes = [];
        if ($isAmbassador) {
            $mailboxes = $this->db->fetchAll('
                SELECT 	m.id,
		                m.name,
		                b.email_name
                FROM fs_bezirk b
                JOIN fs_mailbox m ON b.mailbox_id = m.id
                JOIN fs_botschafter bot ON bot.bezirk_id = b.id
                WHERE bot.foodsaver_id = :fsId;
            ', [
                ':fsId' => $fsId
            ]);
        }

        if ($memberMailbox = $this->db->fetchAll(
            '
			SELECT 	mb.`name`,
					mb.`id`,
					mm.email_name
			FROM	`fs_mailbox` mb,
					`fs_mailbox_member` mm
			WHERE 	mm.mailbox_id = mb.id
			AND 	mm.foodsaver_id = :fs_id
		',
            [':fs_id' => $fsId]
        )) {
            $mailboxes = array_merge($mailboxes, $memberMailbox);
        }

        if ($personalMailbox = $this->db->fetch(
            '
				SELECT 		m.`id`,
							m.name,
							CONCAT(fs.`name`," ",fs.`nachname`) AS email_name
				FROM 		`fs_mailbox` m,
							`fs_foodsaver` fs
				WHERE 		fs.mailbox_id = m.id
				AND 		fs.id = :fs_id
			',
            [':fs_id' => $fsId]
        )) {
            $mailboxes[] = $personalMailbox;
        }

        return array_map(fn ($mailbox) => Mailbox::create($mailbox['id'], $mailbox['name'], $mailbox['email_name']), $mailboxes);
    }

    public function getMailboxId(int $mid)
    {
        try {
            return $this->db->fetchValueByCriteria('fs_mailbox_message', 'mailbox_id', ['id' => $mid]);
        } catch (Exception) {
            return 0;
        }
    }

    /**
     * Returns the mailbox ID and attachment info for the message ID. The attachment info is a json encoded list that
     * contains 'filename', 'origname', and 'mime' for each attachment.
     *
     * @return array
     */
    public function getAttachmentFileInfo(int $messageId)
    {
        return $this->db->fetchByCriteria('fs_mailbox_message', ['mailbox_id', 'attach'], ['id' => $messageId]);
    }

    /**
     * Returns the folder of the mail with this message ID.
     */
    public function getMailFolderId(int $messageId): int
    {
        return $this->db->fetchValueByCriteria('fs_mailbox_message', 'folder', ['id' => $messageId]);
    }

    /**
     * Creates a Mailbox for the user and returns its ID. This function makes sure that the name does not exist yet
     * or changes it to be unique.
     */
    public function createMailbox(string $name): int
    {
        /* Find the highest numeric suffix for mailboxes starting with the given name.
        * For performance, narrow down the search space with LIKE first to names starting with the given name.
        * (assuming the condition actually short-circuits)
        * Then filter the remaining rows with RLIKE to only include those ending with numeric suffixes.
        * Then cast suffixes to integers and find the MAX value.
        */
        $result = $this->db->fetch(
            'SELECT
                MAX(CAST(NULLIF(SUBSTRING(name, :suffix_pos), "") AS UNSIGNED)) AS max_suffix,
                COUNT(*) AS count
            FROM fs_mailbox
            WHERE
                name LIKE :name_prefix AND
                name RLIKE :name_regex
            ', [
                'suffix_pos' => strlen($name) + 1,
                'name_prefix' => preg_replace('/([%_])/', '\\\\$1', $name) . '%',
                'name_regex' => '^' . preg_quote($name) . '\\d*$',
            ],
        );

        if ($result['count'] === 0) {
            // No mailbox with that name exists yet.
            $mailboxName = $name;
        } else {
            // One or more mailboxes with the same name exist, add suffix starting at 1.
            // Suffix may be null if there is only one existing mailbox.
            $number = (int)($result['max_suffix'] ?? 0);
            $mailboxName = $name . ($number + 1);
        }

        // strip_tags should never strip anything here, so should not cause collisions.
        return $this->db->insert('fs_mailbox', ['name' => strip_tags($mailboxName)]);
    }

    /**
     * Converts a JSON string into an email address DTO. Returns null if the JSON cannot be parsed.
     */
    private function parseAddress(string $json): ?EmailAddress
    {
        try {
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR + JSON_INVALID_UTF8_IGNORE);
            $name = $data['personal'] ?? null;

            return new EmailAddress($data['mailbox'], $data['host'] ?? '', $name);
        } catch (Exception) {
            return null;
        }
    }

    /**
     * Converts a JSON string into an array of email address DTOs. Returns null if the JSON cannot be parsed.
     *
     * @return ?EmailAddress[]
     */
    private function parseAddresses(string $json): ?array
    {
        try {
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR + JSON_INVALID_UTF8_IGNORE);

            return array_map(function ($x) {
                $name = $x['personal'] ?? null;

                return new EmailAddress($x['mailbox'], $x['host'] ?? '', $name);
            }, $data);
        } catch (Exception) {
            return null;
        }
    }

    /**
     * Converts an email address DTO into a JSON string.
     */
    private function formatAddress(EmailAddress $address): string
    {
        return json_encode([
            'mailbox' => $address->getMailbox(),
            'host' => $address->getHostname(),
            'personal' => $address->getName()
        ]);
    }

    /**
     * Converts an array of email address DTOs into a JSON string.
     */
    private function formatAddresses(array $addresses): string
    {
        $mapped = array_map(fn ($a) => [
            'mailbox' => $a->getMailbox(),
            'host' => $a->getHostname(),
            'personal' => $a->getName()
        ], $addresses);

        return json_encode($mapped);
    }

    /**
     * Converts a data array from the database into an Email object.
     */
    private function parseEmail(array $data): Email
    {
        // convert the data to an Email object
        $from = $this->parseAddress($data['sender']) ?? new EmailAddress('');
        $to = $this->parseAddresses($data['to']) ?? [];
        $email = Email::create(
            intval($data['id']), intval($data['mailbox_id']), intval($data['folder']),
            $from, $to,
            Carbon::createFromTimestamp($data['time_ts'], new DateTimeZone('Europe/Berlin')), $data['subject'],
            $data['body'] ?? null, $data['body_html'] ?? null,
            $data['read'] > 0, $data['answer'] > 0
        );

        // parse the attachments
        if (!empty($data['attach'])) {
            $attach = json_decode((string)$data['attach'], true);
            if (!empty($attach)) {
                $email->attachments = array_map(function ($a) {
                    $a = $this->fixAttachment($a);

                    return EmailAttachment::create($a['origname'], $a['filename'], -1, $a['mime']);
                }, $attach);
            }
        }

        return $email;
    }

    /**
     * Tries to fix the data of an attachment in case the data is corrupted and some of the keys are missing. The
     * returned array can safely be parsed into an EmailAttachment.
     *
     * @param array $attachment an email attachment from the database
     *
     * @return array the same array with fixed keys
     */
    private function fixAttachment(array $attachment): array
    {
        // replace the missing original file name by the hashed file name
        if (!isset($attachment['origname'])) {
            if (isset($attachment['filename'])) {
                $attachment['origname'] = $attachment['filename'];
            }
        }

        // replace the missing mime type by a generic one
        if (!isset($attachment['mime'])) {
            $attachment['mime'] = 'application/octet-stream';
        }

        return $attachment;
    }

    /**
     * @throws UniqueConstraintViolationException
     */
    public function setRegionMailbox(RegionForAdministration $region): void
    {
        $mailboxId = $this->db->fetchValueById('fs_bezirk', 'mailbox_id', $region->id);
        if ($mailboxId && !$region->mailbox) {
            throw new BadRequestHttpException('Mailbox cannot be removed.');
        }
        if ($mailboxId) {
            $this->db->update('fs_mailbox', ['name' => $region->mailbox], ['id' => $mailboxId]);
        } elseif ($region->mailbox) {
            $mailboxId = $this->db->insert('fs_mailbox', ['name' => $region->mailbox]);
            $this->db->update('fs_bezirk', ['mailbox_id' => $mailboxId], ['id' => $region->id]);
        }
    }

    public function isMailboxNameUsed(string $mailboxName): bool
    {
        return $this->db->count('fs_mailbox', ['name' => $mailboxName]) > 0;
    }
}
