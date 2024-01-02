<?php

namespace Foodsharing\Entrypoint;

use Foodsharing\Lib\Caching;
use Foodsharing\Lib\Db\Mem;
use Foodsharing\Lib\Session;
use Foodsharing\Lib\Xhr\XhrMethods;
use Foodsharing\Lib\Xhr\XhrResponses;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class XhrController extends AbstractController
{
    public function __invoke(
        Request $request,
        Session $session,
        Mem $mem,
        XhrMethods $xhr
    ): Response {
        $session->initIfCookieExists();

        // is this actually used anywhere? (prod?)
        global $g_page_cache;
        if (isset($g_page_cache)) {
            $cache = new Caching($g_page_cache, $session, $mem);
            $cache->lookup();
        }

        $action = $request->query->get('f');

        if ($action === null) {
            return new Response(null, Response::HTTP_BAD_REQUEST);
        }

        if (!$session->isValidCsrfHeader()) {
            $response = new Response();
            $response->setProtocolVersion('1.1');
            $response->setStatusCode(Response::HTTP_FORBIDDEN);
            $response->setContent('CSRF Failed: CSRF token missing or incorrect.');

            return $response;
        }

        $func = 'xhr_' . $action;
        if (!method_exists($xhr, $func)) {
            return new Response(null, Response::HTTP_BAD_REQUEST);
        }

        $response = new Response();

        ob_start();
        echo $xhr->$func($_GET);
        $page = ob_get_contents();
        ob_end_clean();

        if ($page === XhrResponses::PERMISSION_DENIED) {
            $response->setProtocolVersion('1.1');
            $response->setStatusCode(Response::HTTP_FORBIDDEN);
            $response->setContent('Permission denied');

            return $response;
        }

        if (is_string($page) && (!trim($page) || $page[0] == '{' || $page[0] == '[')) {
            // just assume it's JSON, to prevent the browser from interpreting it as
            // HTML, which could result in XSS possibilities
            $response->headers->set('Content-Type', 'application/json');
        }

        // check for page caching
        if (isset($cache) && $cache->shouldCache()) {
            $cache->cache($page);
        }

        $response->setContent($page);

        return $response;
    }
}
