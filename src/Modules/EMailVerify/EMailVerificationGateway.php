<?php

namespace Foodsharing\Modules\EMailVerify;

use Exception;
use Foodsharing\Modules\Core\BaseGateway;
use Foodsharing\Modules\Core\Database;

class EMailVerificationGateway extends BaseGateway
{
    public function __construct(Database $db)
    {
        parent::__construct($db);
    }

    /**
     * Returns the id of the user whose account is registered to that email address.
     *
     * @return int|null a user id or null if the address is not registered
     */
    public function findUserByEmail(string $email): ?int
    {
        try {
            return $this->db->fetchValueByCriteria('fs_foodsaver', 'id', ['email' => $email]);
        } catch (Exception $e) {
            // ignore
        }

        return null;
    }
}
