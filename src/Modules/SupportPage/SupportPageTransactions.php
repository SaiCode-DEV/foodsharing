<?php

namespace Foodsharing\Modules\SupportPage;

use Foodsharing\Lib\Session;
use Foodsharing\RestApi\Models\SupportPage\TicketModel;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use ZammadAPIClient\Client;
use ZammadAPIClient\Resource\Ticket;
use ZammadAPIClient\Resource\User;

class MyTicket extends Ticket
{
    public function getData (): array {
        return [
            'values' => $this->getValues(),
            'remote' => $this->getRemoteData(),
            'error' => $this->getError(),
        ];
    }
}

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
     * @throws BadRequestHttpException if the Zammad server cannot be reached
     */
    public function createTicket(TicketModel $ticketModel): array
    {
        $client = new Client(['url' => ZAMMAD_URL, 'http_token' => ZAMMAD_TICKET_TOKEN]);
        $this->createUser($client, $ticketModel);
        return $this->sendTicket($client, $ticketModel);
    }

    private function sendTicket(Client $client, TicketModel $ticketModel): array
    {
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
            'customer_id' => $ticketModel->emailAddress,
            'article' => [
                'content_type' => 'text/plain',
                'subject' => $ticketModel->subject,
                'body' => $ticketModel->body,
                'attachments' => $attachmentData,
                'type' => TicketArticleType::WEB->value,
                'sender' => 'Customer',
                'internal' => false,
            ],
        ];

        $client->setOnBehalfOfUser($ticketModel->emailAddress);
        $ticket = new MyTicket($client);
        $ticket->setValues($ticketData);
        $response = $ticket->save();

        if (!empty($response->getError())) {
            throw new BadRequestHttpException(message: json_encode($ticket->getData()));
        }

        return $ticket->getData();
    }

    /**
     * Makes sure that the user exists in Zammad. The user is identified by the email address from the TicketModel.
     */
    private function createUser(Client $client, TicketModel $ticketModel): void
    {
        $client->unsetOnBehalfOfUser();

        $user = new User($client);
        $user->setValues([
            'firstname' => $ticketModel->firstName,
            'email' => $ticketModel->emailAddress,
            'roles' => ['Customer'],
        ]);

        // If the user already exists, Zammad sends a 503 reponse which we can ignore
        $user->save();
    }
}
