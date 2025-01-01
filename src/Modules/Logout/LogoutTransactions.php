<?php

namespace Foodsharing\Modules\Logout;

use Foodsharing\Lib\Session;
use Foodsharing\Lib\WebSocketConnection;

class LogoutTransactions
{
    public function __construct(
        private readonly WebSocketConnection $webSocketConnection,
        private readonly Session $session
    ) {
    }

    public function logout()
    {
        if ($this->session->id()) {
            $this->webSocketConnection->unregisterSession($this->session->id(), session_id());
        }
        $this->session->logout();
    }
}
