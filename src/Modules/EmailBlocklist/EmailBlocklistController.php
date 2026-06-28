<?php

declare(strict_types=1);

namespace Foodsharing\Modules\EmailBlocklist;

use Foodsharing\Lib\FoodsharingController;
use Foodsharing\Permissions\EmailBlocklistPermissions;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class EmailBlocklistController extends FoodsharingController
{
    public function __construct(
        private readonly EmailBlocklistPermissions $permissions,
    ) {
        parent::__construct();
    }

    #[Route('/admin/emailblocklist', name: 'email_blocklist_admin')]
    public function index(): Response
    {
        if (!$this->permissions->mayAdministrateEmailBlocklist()) {
            return $this->redirect('/dashboard');
        }

        $this->pageHelper->addContent(
            $this->prepareVueComponent('email-blocklist-admin', 'EmailBlocklistAdmin')
        );

        return $this->renderGlobal();
    }
}
