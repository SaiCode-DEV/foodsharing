<?php

namespace Foodsharing\Modules\Login;

use Foodsharing\Lib\Session;

/**
 * This class manages state of an user.
 *
 * It helps to see the last activity (accurisity of day´s) on the platform.
 */
class UserStatusTransactions
{
    final public const SESSION_FIELD_NAME = 'LAST_USER_ACTIVITY';

    public function __construct(
        private readonly LoginGateway $loginGateway,
        private readonly Session $session
    ) {
    }

    /**
     * Updates the last activity state of the user in database and session.
     *
     * The update in database and session is only executed on date change, the time information are unused.
     */
    public function updateLastUserStatus(int $userId)
    {
        $refreshSession = false;
        // load existing data
        if (!$this->session->has(UserStatusTransactions::SESSION_FIELD_NAME)) {
            $last_activity = $this->loginGateway->getLastLogin($userId);
            $refreshSession = true;
        } else {
            $last_activity = $this->session->get(UserStatusTransactions::SESSION_FIELD_NAME);
        }

        // sanitize data
        $lastActivityDataTime = strtotime($last_activity);
        $isInvalidDateInformation = $lastActivityDataTime === false || $last_activity == '0000-00-00 00:00:00';
        $lastActivityDate = date('Y-m-d', $lastActivityDataTime);

        $today = date('Y-m-d');

        // Refresh data
        if ($isInvalidDateInformation || $today != $lastActivityDate) {
            $this->loginGateway->updateLastActivityInDatabase($userId);
            $refreshSession = true;
        }
        if ($refreshSession) {
            $this->session->set(UserStatusTransactions::SESSION_FIELD_NAME, $today);
        }
    }
}
