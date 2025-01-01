<?php

namespace Foodsharing\Modules\Logout;

use Foodsharing\Lib\FoodsharingController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class LogoutController extends FoodsharingController
{
    private const PRIVATE_PAGES = [
            'betrieb',
            'bezirk',
            'event',
            'foodsaver',
            'fsbetrieb',
            'groups',
            'mailbox',
            'message',
            'quiz',
            'report',
            'settings',
            'store',
        ];

    public function __construct(private readonly LogoutTransactions $logoutTransactions)
    {
        parent::__construct();
    }

    #[Route(path: '/logout', name: 'logout')]
    public function index(Request $request): Response
    {
        $refURI = $request->query->get('ref') ?? '/';
        $page = [];

        $isPregMatchResult = preg_match('~(?s)(?<=\/\?page\=).\w+~i', $refURI, $page);

        if ($isPregMatchResult && in_array($page[0], self::PRIVATE_PAGES)) {
            $refURI = '/';
        }

        $this->logoutTransactions->logout();

        return $this->redirect($refURI);
    }
}
