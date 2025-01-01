<?php

namespace Foodsharing\EventSubscriber;

use Foodsharing\Lib\Session;
use Foodsharing\Lib\WebSocketConnection;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;

/**
 * This eventSubscriber ensure that the session id is offered for the user to the websocket server.
 */
class WebSocketConnectionEventSubscriber implements EventSubscriberInterface
{
    public const SESSION_FIELD = 'WebSocketConnectionRegistrated';

    public function __construct(
        private readonly Session $session,
        private readonly WebSocketConnection $connection
    ) {
    }

    /**
     * Checks if the session is for a logged in user.
     * If the session is not already registrated than the session id is registrated for Websocket server.
     *
     * @param RequestEvent $event Symfony event
     * @return void No handling
     */
    public function onKernelRequest(RequestEvent $event)
    {
        if ($this->session->id() && !$this->session->has(self::SESSION_FIELD)) {
            $this->connection->registerSession($this->session->id(), session_id());
            $this->session->set(self::SESSION_FIELD, true);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            RequestEvent::class => 'onKernelRequest'
        ];
    }
}
