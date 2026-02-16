<?php

namespace Foodsharing\RestApi;

use Foodsharing\Lib\Session;
use Foodsharing\Modules\Core\DBConstants\Mailbox\MailboxFolder;
use Foodsharing\Modules\Core\Pagination;
use Foodsharing\Modules\Mailbox\DTO\Region;
use Foodsharing\Modules\Mailbox\Email;
use Foodsharing\Modules\Mailbox\MailboxGateway;
use Foodsharing\Modules\Mailbox\MailboxTransactions;
use Foodsharing\Permissions\MailboxPermissions;
use Foodsharing\RestApi\Models\Mailbox\EmailSendData;
use Foodsharing\RestApi\Models\Mailbox\PatchEmailModel;
use Nelmio\ApiDocBundle\Annotation\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

#[OA\Tag(name: 'mailbox')]
#[OA\Response(response: Response::HTTP_UNAUTHORIZED, description: 'Not logged in')]
class MailboxRestController extends AbstractFoodsharingRestController
{
    public function __construct(
        protected Session $session,
        private readonly MailboxGateway $mailboxGateway,
        private readonly MailboxPermissions $mailboxPermissions,
        private readonly MailboxTransactions $mailboxTransactions,
    ) {
        parent::__construct($session);
    }

    #[OA\Patch(summary: 'Changes properties of an email.')]
    #[Route('mailboxes/mails/{mailId}', methods: ['PATCH'], requirements: ['mailId' => Requirement::POSITIVE_INT])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success.')]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Not permitted')]
    public function setEmailProperties(int $mailId, #[MapRequestPayload] PatchEmailModel $emailModel): Response
    {
        $this->assertLoggedIn();
        if (!$this->mailboxPermissions->mayMessage($mailId)) {
            throw new AccessDeniedHttpException('Not permitted');
        }

        if (!is_null($emailModel->isRead)) {
            $this->mailboxGateway->markEmailAsRead($mailId, $emailModel->isRead);
        }
        if (!is_null($emailModel->folder)) {
            $this->mailboxGateway->move($mailId, $emailModel->folder);
        }

        return $this->respondOK();
    }

    #[OA\Delete(summary: 'Moves an email to the trash folder or deletes it, if it is already in the trash.')]
    #[Route('mailboxes/mails/{mailId}', methods: ['DELETE'], requirements: ['mailId' => Requirement::POSITIVE_INT])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success')]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Not permitted')]
    public function deleteEmail(int $mailId): Response
    {
        $this->assertLoggedIn();
        if (!$this->mailboxPermissions->mayMessage($mailId)) {
            throw new AccessDeniedHttpException('Not permitted');
        }

        // move or delete the email
        $folder = $this->mailboxGateway->getMailFolderId($mailId);
        if ($folder == MailboxFolder::FOLDER_TRASH) {
            $this->mailboxTransactions->deleteEmail($mailId);
        } else {
            $this->mailboxGateway->move($mailId, MailboxFolder::FOLDER_TRASH);
        }

        return $this->respondOK();
    }

    #[OA\Get(summary: 'Returns the number of unread mails for the sending user.')]
    #[Route('mailboxes/unread-count', methods: ['GET'])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success.', content: new OA\JsonContent(type: 'object',
        properties: [new OA\Property(property: 'unreadCount', type: 'integer', description: 'Number of unread mails')]
    ))]
    public function getUnreadMailCount(): Response
    {
        $this->assertLoggedIn();
        $unread = $this->mailboxGateway->getUnreadMailCount($this->session->id());

        return $this->respondOK(['unreadCount' => $unread]);
    }

    #[OA\Get(summary: 'Returns mails from a mailbox.')]
    #[Route('mailboxes/{mailboxId}/folders/{folderId}/mails', methods: ['GET'], requirements: ['mailboxId' => Requirement::POSITIVE_INT, 'folderId' => Requirement::POSITIVE_INT])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success', content: new OA\JsonContent(
        type: 'array',
        items: new OA\Items(ref: new Model(type: Email::class)))
    )]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Not permitted')]
    public function getAllMailsFromMailbox(int $mailboxId, int $folderId, #[MapQueryParameter] ?int $limit, #[MapQueryParameter] ?int $offset): Response
    {
        $this->assertLoggedIn();

        if (!$this->mailboxPermissions->mayMailbox($mailboxId)) {
            throw new AccessDeniedHttpException('Not permitted');
        }

        $pagination = Pagination::create($limit, $offset);
        $messages = $this->mailboxTransactions->listEmails($mailboxId, $folderId, $pagination);

        return $this->respondOK($messages);
    }

    #[OA\Get(summary: 'Return a mail from mailbox.')]
    #[Route('mailboxes/mails/{mailId}', methods: ['GET'], requirements: ['mailId' => Requirement::POSITIVE_INT])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success.', content: new Model(type: Email::class))]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Not permitted')]
    public function getMail(int $mailId): Response
    {
        $this->assertLoggedIn();

        if (!$this->mailboxPermissions->mayMailbox($this->mailboxGateway->getMailboxId($mailId))) {
            throw new AccessDeniedHttpException('Not permitted');
        }

        $mail = $this->mailboxTransactions->getEmail($mailId);

        return $this->respondOK($mail);
    }

    #[OA\Post(summary: 'Sends an email from a mailbox.')]
    #[Route('mailboxes/{mailboxId}/mails', methods: ['POST'], requirements: ['mailboxId' => Requirement::POSITIVE_INT])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success.', content: new Model(type: Email::class))]
    #[OA\Response(response: Response::HTTP_TOO_MANY_REQUESTS, description: 'Too many requests')]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Not permitted')]
    #[OA\Response(response: Response::HTTP_BAD_REQUEST, description: 'Invalid recipients')]
    #[OA\Response(response: Response::HTTP_NOT_FOUND, description: 'Attachment not found')]
    public function sendMail(int $mailboxId, #[MapRequestPayload] EmailSendData $emailData, Request $request,
        RateLimiterFactory $loginLimiter): Response
    {
        // TODO use correct rate limiter
        $this->checkRateLimit($request, $loginLimiter);

        // check permissions
        $this->assertLoggedIn();

        if (!$this->mailboxPermissions->mayHaveMailbox() || !$this->mailboxPermissions->mayMailbox($mailboxId)) {
            throw new AccessDeniedHttpException('Not permitted');
        }

        $properties = ['to', 'cc', 'bcc'];
        foreach ($properties as $property) {
            if (!empty($emailData->$property)) {
                $emailData->$property = $this->mailboxTransactions->validateRecipients($emailData->$property);
            }
        }

        $email = $this->mailboxTransactions->sendAndSaveEmail($emailData, $mailboxId);

        return $this->respondOK($email);
    }

    #[OA\Get(summary: 'Returns all regions and their email addresses.')]
    #[Route('regions/mailboxes', methods: ['GET'])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success', content: new OA\JsonContent(type: 'array',
        items: new OA\Items(ref: new Model(type: Region::class)))
    )]
    public function listRegions(): Response
    {
        $this->assertLoggedIn();

        $regions = $this->mailboxGateway->getRegionsWithMailAdresses();

        return $this->respondOK($regions);
    }
}
