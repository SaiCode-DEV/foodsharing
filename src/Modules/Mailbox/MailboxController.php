<?php

namespace Foodsharing\Modules\Mailbox;

use Foodsharing\Lib\FoodsharingController;
use Foodsharing\Modules\Core\DBConstants\Foodsaver\Role;
use Foodsharing\Permissions\MailboxPermissions;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;

class MailboxController extends FoodsharingController
{
    public function __construct(
        private readonly MailboxView $view,
        private readonly MailboxGateway $mailboxGateway,
        private readonly MailboxPermissions $mailboxPermissions
    ) {
        parent::__construct();
    }

    #[Route('/mailbox', name: 'mailbox')]
    public function index(Request $request): Response
    {
        $this->commonChecks();

        if ($request->query->get('a') === 'dlattach') {
            return $this->dlattach($request);
        }

        $this->pageHelper->setContentWidth(8, 16);
        $this->pageHelper->addBread($this->translator->trans('mailbox.title'));

        $boxes = $this->mailboxGateway->getBoxes(
            $this->currentUserUnits->isAmbassador(),
            $this->session->id(),
            $this->session->mayRole(Role::STORE_MANAGER)
        );

        $mailboxIds = array_column($boxes, 'id');
        $emailId = $request->query->has('email') ? (int)$request->query->get('email') : null;
        $mailboxId = $request->query->has('mailbox') ? (int)$request->query->get('mailbox') : null;

        if ($emailId && !$mailboxId) {
            $mailboxId = $this->mailboxGateway->getMailboxId($emailId);
        }

        $this->pageHelper->addContent($this->view->vueComponent('vue-mailbox', 'Mailbox', [
            'hostname' => PLATFORM_MAILBOX_HOST,
            'mailboxes' => $this->mailboxGateway->getMailboxesWithUnreadCount($mailboxIds),
            'emailId' => $emailId,
            'mailboxId' => $mailboxId
        ]));

        return $this->renderGlobal();
    }

    /**
     * @deprecated This function is used for downloading attachments of old emails. It can be removed when all files
     *             have been moved to the upload API.
     */
    public function dlattach(Request $request): Response
    {
        $mid = $request->query->get('mid');
        $id = $request->query->get('i');
        if (isset($mid, $id)) {
            if ($m = $this->mailboxGateway->getAttachmentFileInfo((int)$mid)) {
                if ($this->mailboxPermissions->mayMailbox($m['mailbox_id'])) {
                    if ($attach = json_decode((string)$m['attach'], true)) {
                        if (isset($attach[(int)$id])) {
                            $file = 'data/mailattach/' . $attach[(int)$id]['filename'];

                            $filename = $attach[(int)$id]['origname'];
                            $mime = $attach[(int)$id]['mime'];
                            $headers = [];
                            if ($mime) {
                                $headers['Content-Type'] = $mime;
                            }

                            $response = new BinaryFileResponse($file, Response::HTTP_OK, $headers, false);
                            $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $filename);

                            return $response;
                        }
                    }
                }
            }
        }

        return $this->redirectToRoute('mailbox');
    }

    private function commonChecks(): void
    {
        if (!$this->session->mayRole()) {
            $this->routeHelper->goLoginAndExit();
        }

        if (!$this->mailboxPermissions->mayHaveMailbox()) {
            $this->pageHelper->addContent($this->v_utils->v_info($this->translator->trans('mailbox.not-available', [
                '{role}' => '<a href="https://wiki.foodsharing.de/Betriebsverantwortliche*r">' . $this->translator->trans('terminology.storemanager.d') . '</a>',
                '{quiz}' => '<a href="/user/current/settings?sub=rise_role&role=' . Role::STORE_MANAGER->value . '">' . $this->translator->trans('mailbox.sm-quiz') . '</a>',
            ])));
        }
    }
}
