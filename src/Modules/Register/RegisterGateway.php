<?php

namespace Foodsharing\Modules\Register;

use Carbon\Carbon;
use Foodsharing\Modules\Core\BaseGateway;
use Foodsharing\Modules\Core\Database;
use Foodsharing\Modules\Core\DatabaseNoValueFoundException;

class RegisterGateway extends BaseGateway
{
    public function __construct(
        Database $db,
    ) {
        parent::__construct($db);
    }

    public function addRegistrationAttempt(string $email, ?string $token): void
    {
        $this->db->insert('fs_registration_attempt', [
            'email' => $email,
            'token' => $token,
            'valid_until' => Carbon::now()->addHours(REGISTRATION_ATTEMPT_VALIDITY_HOURS),
        ]);
    }

    public function doesRegistrationAttemptExist(string $email): bool
    {
        return $this->db->exists('fs_registration_attempt', ['email' => $email, 'valid_until >' => $this->db->now()]);
    }

    public function getEmailForToken(string $token): ?string
    {
        try {
            return $this->db->fetchValueByCriteria('fs_registration_attempt', 'email', [
                'token' => $token,
                'valid_until >' => $this->db->now()
            ]);
        } catch (DatabaseNoValueFoundException) {
            return null;
        }
    }

    public function deleteRegistrationAttempt(string $token): void
    {
        $this->db->delete('fs_registration_attempt', ['token' => $token]);
    }
}
