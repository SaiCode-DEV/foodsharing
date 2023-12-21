<?php

namespace Foodsharing\RestApi;

use Foodsharing\Lib\Db\Mem;
use Foodsharing\Lib\Session;
use Foodsharing\Modules\Core\DBConstants\Mailbox\MailboxFolder;
use Foodsharing\Modules\Mailbox\Email;
use Foodsharing\Modules\Mailbox\MailboxGateway;
use Foodsharing\Modules\Mailbox\MailboxTransactions;
use Foodsharing\Permissions\MailboxPermissions;
use Foodsharing\RestApi\Models\Mailbox\EmailSendData;
use Foodsharing\RestApi\Models\Mailbox\PatchEmailModel;
use FOS\RestBundle\Controller\AbstractFOSRestController;
use FOS\RestBundle\Controller\Annotations as Rest;
use Nelmio\ApiDocBundle\Annotation\Model;
use OpenApi\Annotations as OA;
use OpenApi\Attributes as OA2;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class MailboxRestController extends AbstractFOSRestController
{
    // private const SECONDS_PER_EMAIL_SENDING = 15;

    public function __construct(
        private readonly MailboxGateway $mailboxGateway,
        private readonly MailboxPermissions $mailboxPermissions,
        private readonly MailboxTransactions $mailboxTransactions,
        private readonly Session $session,
    ) {
    }

    /**
     * Changes properties of an email. This does not care about the previous status, i.e. setting a property to the
     * same value as before will still result in a 'success' response.
     *
     * @OA\Parameter(name="emailId", in="path", @OA\Schema(type="integer"), description="which email to modify")
     * @OA\RequestBody(@Model(type=PatchEmailModel::class))
     * @OA\Response(response="204", description="Success.")
     * @OA\Response(response="400", description="Unknown parameters")
     * @OA\Response(response="403", description="Insufficient permissions to modify the email.")
     * @OA\Response(response="404", description="Email does not exist.")
     * @OA\Tag(name="mailbox")
     * @ParamConverter("emailModel", class="Foodsharing\RestApi\Models\Mailbox\PatchEmailModel", converter="fos_rest.request_body")
     * @Rest\Patch("mailbox/{emailId}", requirements={"emailId" = "\d+"})
     */
    public function setEmailPropertiesAction(int $emailId, PatchEmailModel $emailModel, ValidatorInterface $validator): Response
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

    /**
     * Moves an email to the trash folder or deletes it, if it is already in the trash.
     *
     * @OA\Parameter(name="emailId", in="path", @OA\Schema(type="integer"), description="which email to delete")
     * @OA\Response(response="200", description="Success")
     * @OA\Response(response="403", description="Insufficient permissions to delete the email")
     * @OA\Tag(name="mailbox")
     * @Rest\Delete("mailbox/{emailId}", requirements={"emailId" = "\d+"})
     */
    public function deleteEmailAction(int $emailId): Response
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

    /**
     * Returns the number of unread mails for the sending user.
     *
     * @OA\Response(response="200", description="Success.")
     * @OA\Response(response="401", description="Not logged in.")
     * @OA\Tag(name="mailbox")
     * @Rest\Get("mailbox/unread-count")
     */
    public function getUnreadMailCountAction(): Response
    {
        if (!$this->session->id()) {
            throw new UnauthorizedHttpException('', 'Not logged in.');
        }
        $unread = $this->mailboxGateway->getUnreadMailCount($this->session);

        return $this->handleView($this->view($unread, 200));
    }

    /**
     * Returns all mails from mailbox.
     *
     * @OA\Response(
     * 		response="200",
     * 		description="Success.",
     *      @OA\JsonContent(
     *        type="array",
     *        @OA\Items(ref=@Model(type=Email::class))
     *      ))
     * )
     * @OA\Response(response="401", description="Not logged in.")
     * @OA\Response(response="403", description="Insufficient permissions to read mails from mailbox")
     * @OA\Tag(name="mailbox")
     * @Rest\Get("mailbox/all/{mailboxId}/{folderId}", requirements={"mailboxId" = "\d+"})
     */
    public function getAllMailsFromMailboxAction(int $mailboxId, int $folderId): Response
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

    /**
     * Return mail from mailbox.
     *
     * @OA\Response(
     * 		response="200",
     * 		description="Success.",
     *      @Model(type=Email::class)
     * )
     * @OA\Response(response="401", description="Not logged in.")
     * @OA\Response(response="403", description="Insufficient permissions to read mail from mailbox")
     * @OA\Tag(name="mailbox")
     * @Rest\Get("mailbox/{mailId}", requirements={"mailId" = "\d+"})
     */
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

    /**
     * Send email from mailbox.
     *
     * @OA\RequestBody(@Model(type=EmailSendData::class))
     * @OA\Response(
     * 		response="200",
     * 		description="Success.",
     *      @Model(type=Email::class)
     * )
     * @OA\Response(response="401", description="Not logged in.")
     * @OA\Response(response="403", description="Insufficient permissions to read mail from mailbox")
     * @OA\Response(response="404", description="At least one of the attachments was not found")
     * @OA\Tag(name="mailbox")
     * @ParamConverter("emailData", class="Foodsharing\RestApi\Models\Mailbox\EmailSendData", converter="fos_rest.request_body")
     * @Rest\Post("mailbox/{mailboxId}", requirements={"mailboxId" = "\d+"})
     */
    public function sendMail(int $mailboxId, EmailSendData $emailData, ValidatorInterface $validator): Response
    {
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

        /* ToDo: Disabled because tests aren't running. We want change to symfony rate limiter
        $this->checkEmailSendingRateLimit(); */

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

    /*
     * Simple rate limiter that stores the last time an email was sent by the user in Redis.
     */
    /* private function checkEmailSendingRateLimit()
     {
         if ($last = (int)$this->mem->user($this->session->id(), 'mailbox-last')) {
             if ((time() - $last) < self::SECONDS_PER_EMAIL_SENDING) {
                 throw new AccessDeniedHttpException($this->translator->trans('mailbox.ratelimit'));
             }
         }

         $this->mem->userSet($this->session->id(), 'mailbox-last', time());
     } */

    /**
     * Returns all regions and their email addresses.
     */
    #[OA2\Tag(name: 'mailbox')]
    #[Rest\Get('mailbox/regions')]
    #[OA2\Response(response: Response::HTTP_OK, description: 'Success')]
    #[OA2\Response(response: Response::HTTP_UNAUTHORIZED, description: 'Not logged in')]
    public function listRegions(): Response
    {
        if (!$this->session->mayRole()) {
            throw new UnauthorizedHttpException('');
        }

        $regions = $this->mailboxGateway->getRegionsWithMailAdresses();

        return $this->handleView($this->view($regions, Response::HTTP_OK));
    }
}
