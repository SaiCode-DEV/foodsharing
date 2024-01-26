<?php

namespace Foodsharing\Modules\Relogin;

use Foodsharing\Modules\Core\Control;

class ReloginControl extends Control
{
    public function index()
    {
        try {
            $this->session->refreshFromDatabase();

            if (isset($_GET['url']) && !empty($_GET['url'])) {
                $url = urldecode((string)$_GET['url']);
                if (!str_starts_with($url, 'http')) {
                    $this->routeHelper->goAndExit($url);
                }
            }
            $this->routeHelper->goAndExit('/?page=dashboard');
        } catch (\Exception) {
            $this->routeHelper->goPageAndExit('logout');
        }
    }
}
