<?php

namespace Foodsharing\Modules\Login;

use Foodsharing\Modules\Core\BaseGateway;
use Foodsharing\Modules\Core\Database;
use Foodsharing\Modules\Legal\LegalGateway;
use Foodsharing\Modules\Register\DTO\RegisterData;
use Foodsharing\Utility\EmailHelper;
use Symfony\Contracts\Translation\TranslatorInterface;

class LoginGateway extends BaseGateway
{
    private readonly LegalGateway $legalGateway;
    private readonly EmailHelper $emailHelper;
    private readonly TranslatorInterface $translator;

    public function __construct(
        Database $db,
        LegalGateway $legalGateway,
        EmailHelper $emailHelper,
        TranslatorInterface $translator
    ) {
        $this->legalGateway = $legalGateway;
        $this->emailHelper = $emailHelper;
        $this->translator = $translator;

        parent::__construct($db);
    }

    public function login(string $email, string $pass)
    {
        $email = trim($email);
        if ($this->db->exists('fs_email_blacklist', ['email' => $email])) {
            return null;
        }
        if ($fsid = $this->checkClient($email, $pass)) {
            $this->db->update(
                'fs_foodsaver',
                ['last_login' => $this->db->now()],
                ['id' => $fsid]
            );

            return $fsid;
        }

        return null;
    }

    public function updateLastActivityInDatabase(int $fsid)
    {
        $this->db->update(
            'fs_foodsaver',
            ['last_login' => $this->db->now()],
            ['id' => $fsid]
        );

        return $fsid;
    }

    public function getLastLogin(int $fsId): string
    {
        return $this->db->fetchValueByCriteria('fs_foodsaver', 'last_login', ['id' => $fsId]);
    }

    public function isActivated(int $fsId): bool
    {
        $isActivated = $this->db->fetchValueByCriteria('fs_foodsaver', 'active', ['id' => $fsId]);

        return $isActivated === 1;
    }

    /**
     * Check given email and password combination,
     * update password if old-style one is detected.
     */
    public function checkClient(string $email, $pass = false)
    {
        $email = trim($email);
        if (strlen($email) < 2 || strlen((string)$pass) < 1) {
            return false;
        }

        $user = $this->db->fetchByCriteria(
            'fs_foodsaver',
            ['id', 'password'],
            ['email' => $email, 'deleted_at' => null]
        );

        // does the email exist?
        if (!$user) {
            return false;
        }

        // modern hashing algorithm
        if ($user['password']) {
            if (password_verify((string)$pass, (string)$user['password'])) {
                return $user['id'];
            }
        }

        return false;
    }

    /**
     * hashes password with modern hashing algorithm.
     */
    public function password_hash($password)
    {
        return password_hash((string)$password, PASSWORD_ARGON2I);
    }

    public function activate(string $email, string $token): bool
    {
        return $this->db->update('fs_foodsaver', ['active' => 1], ['email' => strip_tags($email), 'token' => strip_tags($token)]) > 0;
    }

    public function insertNewUser(RegisterData $data, string $token): int
    {
        return $this->db->insert(
            'fs_foodsaver',
            [
                'rolle' => 0,
                'active' => 0,
                'email' => strip_tags((string)$data->email),
                'password' => strip_tags((string)$this->password_hash($data->password)),
                'name' => strip_tags((string)$data->firstName),
                'nachname' => strip_tags((string)$data->lastName),
                'geb_datum' => $data->birthday,
                'handy' => strip_tags((string)$data->mobilePhone),
                'newsletter' => (int)$data->subscribeNewsletter,
                'geschlecht' => (int)$data->gender,
                'anmeldedatum' => $this->db->now(),
                'privacy_policy_accepted_date' => $this->legalGateway->getPpVersion(),
                'token' => strip_tags($token),
            ]
        );
    }

    public function checkResetKey(string $key): bool
    {
        return $this->db->exists('fs_pass_request', ['name' => strip_tags($key)]);
    }

    public function newPassword(array $data)
    {
        if (strlen((string)$data['pass1']) <= 4) {
            return false;
        }

        $fsid = $this->db->fetchValueByCriteria(
            'fs_pass_request',
            'foodsaver_id',
            ['name' => strip_tags((string)$data['k'])]
        );
        if (!$fsid) {
            return false;
        }

        $this->db->delete('fs_pass_request', ['foodsaver_id' => (int)$fsid]);
        $this->setPassword((int)$fsid, (string)$data['pass1']);

        return true;
    }

    public function getMailActivationData(int $fsId): array
    {
        return $this->db->fetchByCriteria(
            'fs_foodsaver',
            ['email', 'token', 'name', 'geschlecht', 'active'],
            ['id' => $fsId]
        );
    }

    public function updateMailActivationToken(int $fsId, string $token): int
    {
        return $this->db->update('fs_foodsaver', ['token' => $token], ['id' => $fsId]);
    }

    public function addPassRequest(string $email, bool $mail = true)
    {
        $fs = $this->db->fetchByCriteria(
            'fs_foodsaver',
            ['id', 'email', 'name', 'geschlecht'],
            ['deleted_at' => null, 'email' => strip_tags($email)]
        );
        if (!$fs) {
            return false;
        }

        $key = bin2hex(random_bytes(16));

        $this->db->insertOrUpdate('fs_pass_request', [
            'foodsaver_id' => $fs['id'],
            'name' => $key,
            'time' => $this->db->now()
        ]);

        if ($mail) {
            $vars = [
                'link' => BASE_URL . '/login?sub=passwordReset&k=' . $key,
                'name' => $fs['name'],
                'anrede' => $this->translator->trans('salutation.' . $fs['geschlecht']),
            ];

            $this->emailHelper->tplMail('user/reset_password', $fs['email'], $vars, false, true);

            return true;
        }

        return $key;
    }

    public function setPassword(int $userId, string $password): void
    {
        $this->db->update('fs_foodsaver', [
            'password' => strip_tags((string)$this->password_hash($password))
        ], ['id' => $userId]);
    }
}
