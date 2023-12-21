<?php

namespace Foodsharing\Modules\Mailbox;

use Carbon\Carbon;
use Ddeboer\Imap\Message\EmailAddress;
use Exception;
use Foodsharing\Lib\Session;
use Foodsharing\Modules\Core\BaseGateway;
use Foodsharing\Modules\Mailbox\DTO\Region;

class MailboxGateway extends BaseGateway
{
    public function getMailboxname(int $mailbox_id)
    {
        try {
            return $this->db->fetchValueByCriteria('fs_mailbox', 'name', ['id' => $mailbox_id]);
        } catch (\Exception $e) {
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
        } catch (Exception $e) {
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
            return Region::create($region['id'], $region['name'], $region['parent_id'], $region['type'],
                $region['email'], $region['email_name']);
        }, $regions);
    }

    public function addMailbox(string $name, int $member = 0): int
    {
        return $this->db->insert('fs_mailbox', ['name' => strip_tags($name), 'member' => $member]);
    }

    public function getMailboxesWithUnreadCount(array $mailboxIds): array
    {
        return $this->db->fetchAll('
			SELECT	mb.id,
					mb.name,
					(
						SELECT	COUNT(*) FROM fs_mailbox_message mm
						WHERE	mb.id = mm.mailbox_id
						AND		mm.read = 0
					) AS count

			FROM	fs_mailbox mb

			WHERE	mb.id IN(' . implode(',', $mailboxIds) . ');
		');
    }

    public function getUnreadMailCount(Session $session): int
    {
        return $this->db->fetchValue(
            'SELECT COUNT(*) FROM `fs_mailbox_message` m WHERE m.`read` = 0 AND m.`mailbox_id` IN (
				SELECT r.`mailbox_id`
				FROM fs_bezirk r
				JOIN fs_botschafter a
				ON a.`foodsaver_id` = :fs_id1 AND r.`id` = a.`bezirk_id` AND r.`mailbox_id` IS NOT NULL
			UNION
				SELECT f.`mailbox_id`
				FROM fs_foodsaver f
				WHERE f.`id` = :fs_id2 AND f.`mailbox_id` IS NOT NULL
			UNION
				SELECT m.`mailbox_id`
				FROM `fs_mailbox_member` m
				WHERE m.`foodsaver_id` = :fs_id3
			)',
            [
                ':fs_id1' => $session->id(),
                ':fs_id2' => $session->id(),
                ':fs_id3' => $session->id()
            ]
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

    /**
     * @deprecated use getEmail instead
     */
    public function getMessage(int $message_id)
    {
        $data = $this->db->fetch(
            '
			SELECT 	m.`id`,
					m.`folder`,
					m.`sender`,
					m.`to`,
					m.`subject`,
					m.`time`,
					UNIX_TIMESTAMP(m.`time`) AS time_ts,
					m.`attach`,
					m.`read`,
					m.`answer`,
					m.`body`,
					m.`mailbox_id`,
					b.name AS mailbox
			FROM 	fs_mailbox_message m
			LEFT JOIN fs_mailbox b
			ON m.mailbox_id = b.id
			WHERE	m.id = :message_id
		',
            [':message_id' => $message_id]
        );

        $data['sender'] = $this->parseAddress($data['sender']) ?? new EmailAddress('');
        $data['to'] = $this->parseAddresses($data['to']) ?? [];

        return $data;
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
					m.body_html,
					m.`mailbox_id`
			FROM 	fs_mailbox_message m
			LEFT JOIN fs_mailbox b
			ON m.mailbox_id = b.id
			WHERE	m.id = :message_id
		',
            [':message_id' => $emailId]
        );

        return $this->parseEmail($data);
    }

    public function markEmailAsRead(int $emailId, bool $isRead): void
    {
        $this->db->update('fs_mailbox_message', ['read' => $isRead ? 1 : 0], ['id' => $emailId]);
    }

    /**
     * @deprecated use listEmails instead
     */
    public function listMessages(int $mailbox_id, int $folder): array
    {
        $data = $this->db->fetchAll(
            '
			SELECT 	`id`,
					`folder`,
					`sender`,
					`to`,
					`subject`,
					`time`,
					UNIX_TIMESTAMP(`time`) AS time_ts,
					`attach`,
					`read`,
					`answer`
			FROM 	fs_mailbox_message
			WHERE	mailbox_id = :mailbox_id
			AND 	folder = :farray_folder
			ORDER BY `time` DESC
		',
            [':mailbox_id' => $mailbox_id, ':farray_folder' => $folder]
        );

        foreach ($data as &$d) {
            $d['sender'] = $this->parseAddress($d['sender']) ?? new EmailAddress('');
            $d['to'] = $this->parseAddresses($d['to']) ?? [];
        }

        return $data;
    }

    /**
     * Returns all emails from a folder of a mailbox without the emails' body.
     *
     * @return Email[]
     */
    public function listEmails(int $mailboxId, int $folder): array
    {
        $data = $this->db->fetchAll(
            '
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
		',
            [':mailbox_id' => $mailboxId, ':farray_folder' => $folder]
        );

        return array_map(function ($x) {
            return $this->parseEmail($x);
        }, $data);
    }

    public function saveMessage(Email $email): int
    {
        $from = $this->formatAddress($email->from);
        $to = $this->formatAddresses($email->to);

        // convert attachments into an array that is stored as json in the database
        $attachments = array_map(function ($a) {
            return [
                'origname' => $a->fileName,
                'filename' => $a->hashedFileName,
                'mime' => $a->mimeType
            ];
        }, $email->attachments ?? []);

        return $this->db->insert(
            'fs_mailbox_message',
            [
                'mailbox_id' => $email->mailboxId,
                'folder' => $email->mailboxFolder,
                'sender' => $from,
                'to' => $to,
                'subject' => strip_tags($email->subject),
                'body' => strip_tags($email->body),
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
            } catch (Exception $e) {
            }

            try {
                $mb['email_name'] = $this->db->fetchValueByCriteria(
                    'fs_bezirk',
                    'email_name',
                    ['mailbox_id' => $mb_id]
                );

                return $mb;
            } catch (Exception $e) {
            }

            try {
                $mb['email_name'] = $this->db->fetchValue(
                    'SELECT email_name FROM fs_mailbox_member WHERE mailbox_id = :mb_id AND email_name != "" LIMIT 1',
                    [':mb_id' => $mb_id]
                );

                return $mb;
            } catch (Exception $e) {
            }
        }

        return false;
    }

    public function getMemberBoxes()
    {
        if ($boxes = $this->db->fetchAllByCriteria('fs_mailbox', ['name', 'id'], ['member' => 1])) {
            foreach ($boxes as $key => $b) {
                $boxes[$key]['email_name'] = '';
                if ($boxes[$key]['member'] = $this->db->fetchAll(
                    '
					SELECT 	fs.id AS id,
							CONCAT(fs.name," ",fs.nachname) AS name,
							mm.email_name
					FROM 	`fs_mailbox_member` mm,
							`fs_foodsaver` fs
					WHERE 	mm.foodsaver_id = fs.id
					AND 	mm.mailbox_id = :b_id
				',
                    [':b_id' => (int)$b['id']]
                )) {
                    foreach ($boxes[$key]['member'] as $mm) {
                        if (!empty($mm['email_name'])) {
                            $boxes[$key]['email_name'] = $mm['email_name'];
                        }
                    }
                }
            }

            return $boxes;
        }

        return false;
    }

    public function updateMember(int $mbid, array $foodsaver): bool
    {
        global $g_data;
        if ($mbid > 0) {
            $this->db->delete('fs_mailbox_member', ['mailbox_id' => $mbid]);

            $insert = [];

            foreach ($foodsaver as $fs) {
                $insert[] = [
                    'mailbox_id' => $mbid,
                    'foodsaver_id' => (int)$fs,
                    'email_name' => '\'' . strip_tags($g_data['email_name']) . '\''
                ];
            }

            $this->db->insertMultiple('fs_mailbox_member', $insert);

            return true;
        }

        return false;
    }

    public function filterName(string $mb_name)
    {
        $mb_name = mb_strtolower($mb_name);
        $mb_name = trim($mb_name);
        $mb_name = str_replace(
            ['ä', 'ö', 'ü', 'è', 'à', 'ß', ' ', '-', '/', '\\'],
            ['ae', 'oe', 'ue', 'e', 'a', 'ss', '.', '.', '.', '.'],
            $mb_name
        );
        $mb_name = preg_replace('/[^0-9a-z\.]/', '', $mb_name);

        if (!empty($mb_name)) {
            return $mb_name;
        }

        return false;
    }

    /**
     * Get region IDs from all member-groups and regions where the user is ambassador / admin.
     */
    private function getMailboxAdminRegions(int $fsId): array
    {
        return $this->db->fetchAllValuesByCriteria('fs_botschafter', 'bezirk_id', ['foodsaver_id' => $fsId]);
    }

    public function getBoxes(bool $isAmbassador, ?int $fsId, bool $mayStoreManager): array
    {
        if ($fsId === null) {
            return [];
        }
        $mBoxes = [];
        if ($isAmbassador) {
            $selectedRegions = $this->getMailboxAdminRegions($fsId);

            if ($selectedRegions) {
                $mailboxAdminRegions = $this->db->fetchAll(
                    '
				SELECT 	`id`,`mailbox_id`,`name`
				FROM 	`fs_bezirk`
				WHERE 	`id` IN (' . implode(',', array_map('intval', $selectedRegions)) . ')
				AND 	`mailbox_id` = 0
			'
                );
                foreach ($mailboxAdminRegions as $region) {
                    if ($region['mailbox_id'] == 0) {
                        $mb_name = mb_strtolower($region['name']);
                        $mb_name = trim($mb_name);
                        $mb_name = str_replace(
                            ['ä', 'ö', 'ü', 'è', 'à', 'ß', ' ', '-', '/', '\\'],
                            ['ae', 'oe', 'ue', 'e', 'a', 'ss', '.', '.', '.', '.'],
                            $mb_name
                        );
                        $mb_name = preg_replace('/[^0-9a-z\.]/', '', $mb_name);

                        if ($mb_name[0] !== '.' && strlen($mb_name) <= 3) {
                            continue;
                        }

                        $mb_id = $this->createMailbox($mb_name);

                        if ($this->db->update('fs_bezirk', ['mailbox_id' => (int)$mb_id], ['id' => (int)$region['id']])) {
                            $region['mailbox_id'] = $mb_id;
                        }
                    }
                }

                $mailboxAdminRegions = $this->db->fetchAll(
                    '
					SELECT 	m.`id`,
							m.`name`,
							b.email_name,
							b.id AS bezirk_id
					FROM 	`fs_bezirk` b,
							`fs_mailbox` m
					WHERE 	b.mailbox_id = m.id
					AND 	b.`id` IN (' . implode(',', array_map('intval', $selectedRegions)) . ')
				'
                );

                foreach ($mailboxAdminRegions as $region) {
                    if (empty($region['email_name'])) {
                        $region['email_name'] = 'foodsharing ' . $region['name'];
                        $this->db->update(
                            'fs_bezirk',
                            ['email_name' => strip_tags($region['email_name'])],
                            ['id' => (int)$region['bezirk_id']]
                        );
                    }
                    $mBoxes[] = [
                        'id' => $region['id'],
                        'name' => $region['name'],
                        'email_name' => $region['email_name'],
                    ];
                }
            }
        }

        $me = [];
        try {
            $me = $this->db->fetchByCriteria(
                'fs_foodsaver',
                ['mailbox_id', 'name', 'nachname'],
                ['id' => $fsId]
            );
        } catch (\Exception $e) {
            // until now it does nothing, if no value is found
        }
        if ($mayStoreManager && $me && $me['mailbox_id'] == 0) {
            $me['name'] = explode(' ', $me['name']);
            $me['name'] = $me['name'][0];

            $me['nachname'] = explode(' ', $me['nachname']);
            $me['nachname'] = $me['nachname'][0];

            $mb_name = mb_strtolower(substr($me['name'], 0, 1) . '.' . $me['nachname']);
            $mb_name = trim($mb_name);
            $mb_name = str_replace(['ä', 'ö', 'ü', 'è', 'ß', ' '], ['ae', 'oe', 'ue', 'e', 'ss', '.'], $mb_name);
            $mb_name = preg_replace('/[^0-9a-z\.]/', '', $mb_name) ?? '';

            $mb_name = substr($mb_name, 0, 25);

            if ($mb_name[0] !== '.' && strlen($mb_name) > 3) {
                $mb_id = $this->createMailbox($mb_name);
                if ($this->db->update('fs_foodsaver', ['mailbox_id' => (int)$mb_id], ['id' => $fsId])) {
                    $me['mailbox_id'] = $mb_id;
                }
            }
        }
        if ($memberb = $this->db->fetchAll(
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
            foreach ($memberb as $m) {
                if (empty($m['email_name'])) {
                    $m['email_name'] = $m['name'] . '@' . PLATFORM_MAILBOX_HOST;
                    $this->db->update(
                        'fs_mailbox_member',
                        ['email_name' => strip_tags($m['name']) . '@' . PLATFORM_MAILBOX_HOST],
                        ['mailbox_id' => (int)$m['id'], 'foodsaver_id' => $fsId]
                    );
                }
                $mBoxes[] = [
                    'id' => $m['id'],
                    'name' => $m['name'],
                    'email_name' => $m['email_name'],
                ];
            }
        }

        if ($mebox = $this->db->fetch(
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
            $mBoxes[] = [
                'id' => $mebox['id'],
                'name' => $mebox['name'],
                'email_name' => $mebox['email_name'],
            ];
        }

        return $mBoxes;
    }

    public function getMailboxId(int $mid)
    {
        try {
            return $this->db->fetchValueByCriteria('fs_mailbox_message', 'mailbox_id', ['id' => $mid]);
        } catch (Exception $e) {
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
     * Returns the HTML body of the mail with this message ID.
     */
    public function getMessageHtmlBody(int $messageId): string
    {
        return $this->db->fetchValueByCriteria('fs_mailbox_message', 'body_html', ['id' => $messageId]);
    }

    /**
     * Creates a Mailbox for the user and returns its ID.
     */
    private function createMailbox(string $name): int
    {
        $amountOfMailboxesStartingWithName = $this->db->fetchValue(
            'SELECT COUNT(name) FROM fs_mailbox WHERE name LIKE :name',
            [
                'name' => $name . '%'
            ]
        );

        $mailboxName = $amountOfMailboxesStartingWithName > 0 ? $name . $amountOfMailboxesStartingWithName : $name;

        return $this->db->insert('fs_mailbox', ['name' => strip_tags($mailboxName)]);
    }

    /**
     * Fixes legacy email address JSON possibly containing quoted keys and/or values.
     */
    private function fixQuotedAddressJson(string $json): string
    {
        return preg_replace('/(?<=\{|,)\\\\"(host|mailbox|personal)\\\\":\\\\"(.*?)\\\\"(?=\}|,\\\\"(?:host|mailbox|personal)\\\\")/', '"$1":"$2"', trim($json, '"'));
    }

    /**
     * Converts a JSON string into an email address DTO. Returns null if the JSON cannot be parsed.
     */
    private function parseAddress(string $json): ?EmailAddress
    {
        try {
            $json = $this->fixQuotedAddressJson($json);
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR + JSON_INVALID_UTF8_IGNORE);
            $name = isset($data['personal']) ? $data['personal'] : null;

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
        $json = $this->fixQuotedAddressJson($json);
        try {
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR + JSON_INVALID_UTF8_IGNORE);

            return array_map(function ($x) {
                $name = isset($x['personal']) ? $x['personal'] : null;

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
        $mapped = array_map(function ($a) {
            return [
                'mailbox' => $a->getMailbox(),
                'host' => $a->getHostname(),
                'personal' => $a->getName()
            ];
        }, $addresses);

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
            Carbon::createFromTimestamp($data['time_ts']), $data['subject'],
            $data['body'] ?? null, $data['body_html'] ?? null,
            $data['read'] > 0, $data['answer'] > 0
        );

        // parse the attachments
        if (!empty($data['attach'])) {
            $attach = json_decode($data['attach'], true);
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
}
