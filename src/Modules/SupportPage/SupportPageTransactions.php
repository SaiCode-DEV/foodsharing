<?php

namespace Foodsharing\Modules\SupportPage;

use Foodsharing\Lib\Session;
use Foodsharing\RestApi\Models\SupportPage\TicketModel;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use ZammadAPIClient\Client;
use ZammadAPIClient\Resource\Ticket;

class SupportPageTransactions
{
    private const GROUP_ID_DEFAULT = 1;

    public function __construct(
        private readonly Session $session,
    ) {
    }

    /**
     * Sends the ticket to the Zammad API and returns the ticket ID.
     *
     * @throws ServiceUnavailableHttpException if the Zammad server cannot be reached
     */
    public function createTicket(TicketModel $ticketModel): void
    {
        $client = new Client(['url' => ZAMMAD_URL, 'http_token' => ZAMMAD_TICKET_TOKEN]);

        $sessionId = $this->session->id() ? ", {$this->session->id()}" : '';
        $fullTitle = "{$ticketModel->subject} ({$ticketModel->firstName}{$sessionId})";

        $attachmentData = array_map(fn ($a) => [
            'filename' => $a->fileName,
            'data' => $a->content,
            'mime-type' => $a->contentType,
        ], $ticketModel->attachments);
        $ticketData = [
            'group_id' => self::GROUP_ID_DEFAULT,
            'title' => $fullTitle,
            'customer_id' => 'guess:' . $ticketModel->emailAddress,
            'article' => [
                'subject' => $ticketModel->subject,
                'body' => $ticketModel->body,
                'attachments' => $attachmentData,
            ],
        ];

        $ticket = new Ticket($client);
        $ticket->setValues($ticketData);
        $response = $ticket->save();

        if (!empty($response->getError())) {
            throw new ServiceUnavailableHttpException(message: $response->getError());
        }
    }
}
