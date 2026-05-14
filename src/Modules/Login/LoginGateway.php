<?php

namespace Foodsharing\Modules\Login;

use Foodsharing\Modules\Core\BaseGateway;
use Foodsharing\Modules\Core\Database;
use Foodsharing\Modules\Core\DatabaseNoValueFoundException;
use Foodsharing\Modules\Profile\DTO\PasswordResetRequest;
use Foodsharing\Modules\Register\DTO\RegisterData;
use Foodsharing\Utility\EmailHelper;
use RobThree\Auth\Providers\Qr\BaconQrCodeProvider;
use RobThree\Auth\TwoFactorAuth;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Contracts\Translation\TranslatorInterface;

class LoginGateway extends BaseGateway
{
    private readonly EmailHelper $emailHelper;
    private readonly TranslatorInterface $translator;

    public function __construct(
        Database $db,
        EmailHelper $emailHelper,
        TranslatorInterface $translator
    ) {
        $this->emailHelper = $emailHelper;
        $this->translator = $translator;

        parent::__construct($db);
    }

    /**
     * Tests if the combination of email, password, and code is allowed to log in. Returns the user's id if so, or null if the
     * combination does not exist or if the user is blacklisted. This does not actually update anything in the database.
     */
    public function canLogin(string $email, string $pass, string $code): ?int
    {
        $email = trim($email);
        if ($this->db->exists('fs_email_blacklist', ['email' => $email])) {
            return null;
        }

        return $this->checkClient($email, $pass, $code);
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
     * Check given email and password combination. Returns the user's id or false, if the combination does not match.
     */
    public function checkClient(string $email, $pass = false, $code = false): int|bool
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
                // Password correct, return user ID if TOTP is NOT enabled
                if (!$this->hasTOTP($user['id'], '')) {
                    return $user['id'];
                }

                // Password correct, but TOTP is enabled -> check TOTP
                if ($this->checkTOTP($user['id'], $code)) {
                    return $user['id'];
                }

                return false;
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

    public function insertNewUser(RegisterData $data, string $email): int
    {
        return $this->db->insert(
            'fs_foodsaver',
            [
                'rolle' => 0,
                'active' => 1,
                'email' => strip_tags($email),
                'password' => strip_tags((string)$this->password_hash($data->password)),
                'name' => strip_tags((string)$data->firstName),
                'nachname' => strip_tags((string)$data->lastName),
                'geb_datum' => $data->birthdate->format('Y-m-d'),
                'handy' => strip_tags((string)$data->mobilePhone),
                'geschlecht' => (int)$data->gender,
                'anmeldedatum' => $this->db->now(),
                'token' => '',
            ]
        );
    }

    public function checkResetKey(string $key): bool
    {
        return $this->db->exists('fs_pass_request', ['name' => strip_tags($key)]);
    }

    public function newPassword(PasswordResetRequest $request): bool
    {
        if (strlen($request->password) <= 4) {
            return false;
        }

        $fsid = $this->db->fetchValueByCriteria(
            'fs_pass_request',
            'foodsaver_id',
            ['name' => strip_tags($request->resetToken)]
        );
        if (!$fsid) {
            return false;
        }

        // Check if user has 2FA enabled. If so, we accept the password change
        // request only if the TOTP code is correct
        if ($this->hasTOTP($fsid, '') && (!isset($request->totpCode) || !$this->checkTOTP($fsid, $request->totpCode))) {
            throw new AccessDeniedHttpException('Invalid TOTP code');
        }

        $this->db->delete('fs_pass_request', ['foodsaver_id' => (int)$fsid]);
        $this->setPassword((int)$fsid, $request->password);

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

        $resetToken = bin2hex(random_bytes(16));

        $this->db->insertOrUpdate('fs_pass_request', [
            'foodsaver_id' => $fs['id'],
            'name' => $resetToken,
            'time' => $this->db->now()
        ]);

        if ($mail) {
            $vars = [
                'link' => BASE_URL . '/password-reset/' . $resetToken,
                'name' => $fs['name'],
                'anrede' => $this->translator->trans('salutation.' . $fs['geschlecht']),
            ];

            // Add TOTP request if user has TOTP enabled
            if ($this->hasTOTP($fs['id'], '')) {
                $vars['link'] .= '?totp=true';
            }

            $this->emailHelper->tplMail('user/reset_password', $fs['email'], $vars, false, true);

            return true;
        }

        return $resetToken;
    }

    public function setPassword(int $userId, string $password): void
    {
        $this->db->update('fs_foodsaver', [
            'password' => strip_tags((string)$this->password_hash($password))
        ], ['id' => $userId]);
    }

    public function setTOTPSecret(int $userId, ?string $secret): void
    {
        $this->db->update('fs_foodsaver', [
            'totp_secret' => $secret ? strip_tags((string)$secret) : null
        ], ['id' => $userId]);
    }

    public function getTOTPSecret(int $userId): ?string
    {
        return $this->db->fetchValueByCriteria(
            'fs_foodsaver',
            'totp_secret',
            ['id' => $userId]
        );
    }

    public function hasTOTP(int $userId, string $email): bool
    {
        // When checking if TOTP is needed, we may not know the user ID yet
        if ($email !== '' || $userId < 0) {
            try {
                $userId = $this->db->fetchValueByCriteria(
                    'fs_foodsaver',
                    'id',
                    [
                        'email' => strip_tags($email),
                        'deleted_at' => null
                    ]
                );
            } catch (DatabaseNoValueFoundException) {
                // User does not exist
                return false;
            }
        }

        // Return whether TOTP secret is set
        return $this->getTOTPSecret($userId) !== null;
    }

    public function setBackupCodes(int $userId, array $codes): void
    {
        // Store backup codes
        $this->db->update('fs_foodsaver',
            ['backup_codes' => empty($codes) ? null : json_encode($codes)],
            ['id' => $userId]);
    }

    public function checkAndRemoveBackupCode(int $userId, string $code): bool
    {
        // Read backup codes
        $data = $this->db->fetchByCriteria(
            'fs_foodsaver',
            ['backup_codes'],
            ['id' => $userId]
        );

        // No backup codes stored
        if (!$data || !isset($data['backup_codes'])) {
            return false;
        }

        // Check if backup codes are a JSON string
        $codes = json_decode($data['backup_codes'], true);
        if (!is_array($codes)) {
            return false;
        }

        // Check if code is valid
        $key = array_search($code, $codes);
        if ($key === false) {
            return false;
        }

        // If we reach this point, the code exists
        // Remove it and store the remaining codes
        unset($codes[$key]);
        $this->setBackupCodes($userId, $codes);

        return true;
    }

    public function checkTOTP(int $userId, string $code): bool
    {
        // Get TOTP secret (at this point we already know it is set)
        $totp_secret = $this->getTOTPSecret($userId);

        // Verify TOTP code
        $qrCodeProvider = new BaconQrCodeProvider();
        $twoFactorAuth = new TwoFactorAuth($qrCodeProvider);
        if ($twoFactorAuth->verifyCode($totp_secret, $code)) {
            return true;
        }

        // On mismatch of TOTP code, check backup codes
        if ($this->checkAndRemoveBackupCode($userId, $code)) {
            return true;
        }

        // If we reach this point, the code is incorrect
        return false;
    }

    /**
     * Validates the given password against a set of constraints.
     *
     * @param string $password the password to validate
     *
     * @return string|null Returns an error message if the password fails any constraint,
     *                     or null if the password meets all criteria.
     *
     * Constraints:
     * - Password must be at least 8 characters long.
     * - Password must not contain leading or trailing whitespace.
     * - Password must contain at least one lowercase letter.
     * - Password must contain at least one uppercase letter.
     * - Password must contain at least one digit.
     */
    public function checkPassword(string $password): ?string
    {
        // Check password length
        if (strlen(trim($password)) < 8) {
            return 'Password is too short';
        }
        // Check if the password is the same after trimming
        if (trim($password) !== $password) {
            return 'Password contains leading or trailing whitespace';
        }
        if (!preg_match('/[a-z]/', $password)) {
            return 'Password must contain at least one lowercase letter';
        }
        if (!preg_match('/[A-Z]/', $password)) {
            return 'Password must contain at least one uppercase letter';
        }
        if (!preg_match('/[0-9]/', $password)) {
            return 'Password must contain at least one digit';
        }

        // All okay
        return null;
    }
}
