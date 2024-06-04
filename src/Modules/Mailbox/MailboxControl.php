<?php

namespace Foodsharing\Modules\Mailbox;

use Foodsharing\Modules\Core\Control;
use Foodsharing\Modules\Core\DBConstants\Foodsaver\Role;
use Foodsharing\Permissions\MailboxPermissions;

class MailboxControl extends Control
{
    private readonly MailboxGateway $mailboxGateway;
    private readonly MailboxPermissions $mailboxPermissions;

    public function __construct(
        MailboxView $view,
        MailboxGateway $mailboxGateway,
        MailboxPermissions $mailboxPermissions
    ) {
        $this->view = $view;
        $this->mailboxGateway = $mailboxGateway;
        $this->mailboxPermissions = $mailboxPermissions;

        parent::__construct();

        if (!$this->session->mayRole()) {
            $this->routeHelper->goLoginAndExit();
        }

        if (!$this->mailboxPermissions->mayHaveMailbox()) {
            $this->pageHelper->addContent($this->v_utils->v_info($this->translator->trans('mailbox.not-available', [
                '{role}' => '<a href="https://wiki.foodsharing.de/Betriebsverantwortliche*r">' . $this->translator->trans('terminology.storemanager.d') . '</a>',
                '{quiz}' => '<a href="/?page=settings&sub=rise_role&role=' . Role::STORE_MANAGER->value . '">' . $this->translator->trans('mailbox.sm-quiz') . '</a>',
            ])));
        }
    }

    public function index()
    {
        $this->pageHelper->setContentWidth(8, 16);
        $this->pageHelper->addBread($this->translator->trans('mailbox.title'));

        $boxes = $this->mailboxGateway->getBoxes(
            $this->currentUserUnits->isAmbassador(),
            $this->session->id(),
            $this->session->mayRole(Role::STORE_MANAGER)
        );

        $mailboxIds = array_column($boxes, 'id');
        $emailId = isset($_GET['email']) ? intval($_GET['email']) : null;
        $mailboxId = isset($_GET['mailbox']) ? intval($_GET['mailbox']) : null;

        if ($emailId && !$mailboxId) {
            $mailboxId = $this->mailboxGateway->getMailboxId($emailId);
        }

        $this->pageHelper->addContent($this->view->vueComponent('vue-mailbox', 'Mailbox', [
            'hostname' => PLATFORM_MAILBOX_HOST,
            'mailboxes' => $this->mailboxGateway->getMailboxesWithUnreadCount($mailboxIds),
            'emailId' => $emailId,
            'mailboxId' => $mailboxId
        ]));
    }

    /**
     * @deprecated This function is used for downloading attachments of old emails. It can be removed when all files
     *             have been moved to the upload API.
     */
    public function dlattach()
    {
        if (isset($_GET['mid'], $_GET['i'])) {
            if ($m = $this->mailboxGateway->getAttachmentFileInfo($_GET['mid'])) {
                if ($this->mailboxPermissions->mayMailbox($m['mailbox_id'])) {
                    if ($attach = json_decode((string)$m['attach'], true)) {
                        if (isset($attach[(int)$_GET['i']])) {
                            $file = 'data/mailattach/' . $attach[(int)$_GET['i']]['filename'];

                            $filename = $attach[(int)$_GET['i']]['origname'];
                            $size = filesize($file);
                            $mime = $attach[(int)$_GET['i']]['mime'];
                            if ($mime) {
                                header('Content-Type: ' . $mime);
                            }
                            header('Content-Disposition: attachment; filename="' . $filename . '"');
                            header('Content-Length: ' . $size);
                            readfile($file);
                            exit;
                        }
                    }
                }
            }
        }

        $this->routeHelper->goPageAndExit('mailbox');
    }
}
