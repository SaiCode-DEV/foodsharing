<?php

namespace Foodsharing\RestApi;

use Foodsharing\Lib\Session;
use Foodsharing\Modules\Core\DBConstants\Mailbox\MailboxFolder;
use Foodsharing\Modules\Mailbox\Email;
use Foodsharing\Modules\Mailbox\MailboxGateway;
use Foodsharing\Modules\Mailbox\MailboxTransactions;
use Foodsharing\Permissions\MailboxPermissions;
use Foodsharing\RestApi\Models\Mailbox\EmailSendData;
use Foodsharing\RestApi\Models\Mailbox\PatchEmailModel;
use FOS\RestBundle\Controller\Annotations as Rest;
use Nelmio\ApiDocBundle\Annotation\Model;
use OpenApi\Attributes as OA;
use OpenApi\Attributes\Parameter;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class MailboxRestController extends AbstractFoodsharingRestController
{
    public function __construct(
        protected Session $session,
        private readonly MailboxGateway $mailboxGateway,
        private readonly MailboxPermissions $mailboxPermissions,
        private readonly MailboxTransactions $mailboxTransactions,
    ) {
    }

    #[OA\Patch(summary: 'Changes properties of an email. This does not care about the previous status, i.e. setting a property to the
     same value as before will still result in a success response.')]
    #[OA\Tag(name: 'mailbox')]
    #[Rest\Patch(path: 'mailbox/{emailId}', requirements: ['emailId' => '\d+'])]
    #[Parameter(name: 'emailId', description: 'which email to modify', in: 'path', schema: new OA\Schema(type: 'integer'))]
    #[OA\RequestBody(content: new Model(type: PatchEmailModel::class))]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success.')]
    #[OA\Response(response: Response::HTTP_BAD_REQUEST, description: 'Unknown parameters')]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Insufficient permissions to modify the email.')]
    #[OA\Response(response: Response::HTTP_NOT_FOUND, description: 'Email does not exist.')]
    #[ParamConverter('emailModel', class: PatchEmailModel::class, converter: 'fos_rest.request_body')]
    public function setEmailProperties(int $emailId, PatchEmailModel $emailModel, ValidatorInterface $validator): Response
    {
        if (!$this->session->id()) {
            throw new UnauthorizedHttpException('');
        }
        if (!$this->mailboxPermissions->mayMessage($emailId)) {
            throw new AccessDeniedHttpException();
        }

        $errors = $validator->validate($emailModel);
        if ($errors->count() > 0) {
            $firstError = $errors->get(0);
            throw new BadRequestHttpException(json_encode(['field' => $firstError->getPropertyPath(), 'message' => $firstError->getMessage()]));
        }

        if (!is_null($emailModel->isRead)) {
            $this->mailboxGateway->markEmailAsRead($emailId, $emailModel->isRead);
        }
        if (!is_null($emailModel->folder)) {
            $this->mailboxGateway->move($emailId, $emailModel->folder);
        }

        return $this->handleView($this->view([], 204));
    }

    #[OA\Delete(summary: 'Moves an email to the trash folder or deletes it, if it is already in the trash.')]
    #[OA\Tag(name: 'mailbox')]
    #[Rest\Delete(path: 'mailbox/{emailId}', requirements: ['emailId' => '\d+'])]
    #[Parameter(name: 'emailId', description: 'which email to delete', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success')]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Insufficient permissions to delete the email')]
    public function deleteEmail(int $emailId): Response
    {
        // check permission
        if (!$this->session->id()) {
            throw new UnauthorizedHttpException('');
        }
        if (!$this->mailboxPermissions->mayMessage($emailId)) {
            throw new AccessDeniedHttpException();
        }

        // move or delete the email
        $folder = $this->mailboxGateway->getMailFolderId($emailId);
        if ($folder == MailboxFolder::FOLDER_TRASH) {
            $this->mailboxTransactions->deleteEmail($emailId);
        } else {
            $this->mailboxGateway->move($emailId, MailboxFolder::FOLDER_TRASH);
        }

        return $this->handleView($this->view([], 200));
    }

    #[OA\Get(summary: 'Returns the number of unread mails for the sending user.')]
    #[OA\Tag(name: 'mailbox')]
    #[Rest\Get(path: 'mailbox/unread-count')]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success.')]
    #[OA\Response(response: Response::HTTP_UNAUTHORIZED, description: 'Not logged in.')]
    public function getUnreadMailCount(): Response
    {
        if (!$this->session->id()) {
            throw new UnauthorizedHttpException('', 'Not logged in.');
        }
        $unread = $this->mailboxGateway->getUnreadMailCount($this->session);

        return $this->handleView($this->view($unread, 200));
    }

    #[OA\Get(summary: 'Returns all mails from mailbox.')]
    #[OA\Tag(name: 'mailbox')]
    #[Rest\Get(path: 'mailbox/all/{mailboxId}/{folderId}', requirements: ['mailboxId' => '\d+'])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success', content: new OA\JsonContent(
        type: 'array',
        items: new OA\Items(ref: new Model(type: Email::class)))
    )]
    #[OA\Response(response: Response::HTTP_UNAUTHORIZED, description: 'Not logged in.')]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Insufficient permissions to read mails from mailbox')]
    public function getAllMailsFromMailbox(int $mailboxId, int $folderId): Response
    {
        if (!$this->session->id()) {
            throw new UnauthorizedHttpException('', 'Not logged in.');
        }

        if (!$this->mailboxPermissions->mayMailbox($mailboxId)) {
            throw new AccessDeniedHttpException();
        }

        $messages = $this->mailboxTransactions->listEmails($mailboxId, $folderId);

        return $this->handleView($this->view($messages, 200));
    }

    #[OA\Get(summary: 'Return mail from mailbox.')]
    #[OA\Tag(name: 'mailbox')]
    #[Rest\Get(path: 'mailbox/{mailId}', requirements: ['mailId' => '\d+'])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success.', content: [new Model(type: Email::class)])]
    #[OA\Response(response: Response::HTTP_UNAUTHORIZED, description: 'Not logged in.')]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Insufficient permissions to read mail from mailbox')]
    public function getMail(int $mailId): Response
    {
        if (!$this->session->id()) {
            throw new UnauthorizedHttpException('', 'Not logged in.');
        }

        if (!$this->mailboxPermissions->mayMailbox($this->mailboxGateway->getMailboxId($mailId))) {
            throw new AccessDeniedHttpException();
        }

        $mail = $this->mailboxTransactions->getEmail($mailId);

        return $this->handleView($this->view($mail, 200));
    }

    #[OA\Post(summary: 'Send email from mailbox.')]
    #[OA\Tag(name: 'mailbox')]
    #[Rest\Post(path: 'mailbox/{mailboxId}', requirements: ['mailboxId' => '\d+'])]
    #[OA\RequestBody(content: new Model(type: EmailSendData::class))]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success.', content: [new Model(type: Email::class)])]
    #[OA\Response(response: Response::HTTP_UNAUTHORIZED, description: 'Not logged in.')]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Insufficient permissions to read mail from mailbox')]
    #[OA\Response(response: Response::HTTP_NOT_FOUND, description: 'At least one of the attachments was not found')]
    #[ParamConverter('emailData', class: EmailSendData::class, converter: 'fos_rest.request_body')]
    public function sendMail(int $mailboxId, EmailSendData $emailData, ValidatorInterface $validator, Request $request,
        RateLimiterFactory $loginLimiter): Response
    {
        $this->checkRateLimit($request, $loginLimiter);

        // check permissions
        if (!$this->session->id()) {
            throw new UnauthorizedHttpException('', 'Not logged in.');
        }

        if (!$this->mailboxPermissions->mayHaveMailbox() || !$this->mailboxPermissions->mayMailbox($mailboxId)) {
            throw new AccessDeniedHttpException();
        }

        $mailbox = $this->mailboxGateway->getMailbox($mailboxId);
        if (!$mailbox) {
            throw new NotFoundHttpException();
        }

        // check validity of parameters
        $errors = $validator->validate($emailData);
        if ($errors->count() > 0) {
            $firstError = $errors->get(0);
            throw new BadRequestHttpException(json_encode(['field' => $firstError->getPropertyPath(), 'message' => $firstError->getMessage()]));
        }

        $properties = ['to', 'cc', 'bcc'];

        foreach ($properties as $property) {
            if (!empty($emailData->$property)) {
                $emailData->$property = $this->mailboxTransactions->validateRecipients($emailData->$property);
            }
        }

        $email = $this->mailboxTransactions->sendAndSaveEmail($emailData, $mailboxId);

        return $this->handleView($this->view($email, Response::HTTP_CREATED));
    }

    #[OA\Get(summary: 'Returns all regions and their email addresses.')]
    #[OA\Tag(name: 'mailbox')]
    #[Rest\Get('mailbox/regions')]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success')]
    #[OA\Response(response: Response::HTTP_UNAUTHORIZED, description: 'Not logged in')]
    public function listRegions(): Response
    {
        if (!$this->session->mayRole()) {
            throw new UnauthorizedHttpException('');
        }

        $regions = $this->mailboxGateway->getRegionsWithMailAdresses();

        return $this->handleView($this->view($regions, Response::HTTP_OK));
    }
}
