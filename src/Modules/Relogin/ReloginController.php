<?php

namespace Foodsharing\Modules\Relogin;

use Foodsharing\Lib\FoodsharingController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ReloginController extends FoodsharingController
{
    #[Route(path: '/relogin', name: 'relogin')]
    public function index(Request $request): Response
    {
        try {
            $this->session->refreshFromDatabase();

            if ($request->query->has('url') && !empty($request->query->get('url'))) {
                $url = urldecode((string)$request->query->get('url'));
                if (!str_starts_with($url, 'http')) {
                    $this->routeHelper->goAndExit($url);
                }
            }

            return $this->redirectToRoute('dashboard');
        } catch (\Exception) {
            return $this->redirect('/logout');
        }
    }
}
