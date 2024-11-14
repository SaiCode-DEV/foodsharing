<?php

namespace Foodsharing\EventSubscriber;

use Foodsharing\Lib\Caching;
use Foodsharing\Lib\ContentSecurityPolicy;
use Foodsharing\Lib\Db\Mem;
use Foodsharing\Lib\FoodsharingController;
use Foodsharing\Lib\Session;
use Foodsharing\Modules\Settings\SettingsTransactions;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Definition: "render controller"
 * Any controllers that render parts of the website.
 * Meaning: Symfony Controllers inheriting from FoodsharingController, found under Modules/
 * It does not include anything to do with xhrapp or the REST API.
 *
 * This holds all logic that used to be executed in index.php before calling a Control class.
 * It does this for any Controller that inherits from FoodsharingController.
 *
 * The purpose of this EventSubscriber is to hold common logic executed before any such controller,
 * so it can be shared with any Symfony controllers that were previously legacy controllers called through Entrypoint/IndexController,
 * without breaking any implicit expectations (for example: about the session, certain headers, static parts of the page)
 *
 * The end goal is to find better ways to do some of the things currently done here,
 * or find out if other code can be rewritten to get rid of code here.
 * Some code could certainly be better solved somehow else.
 * Also, for code that continues to be necessary (maybe even for REST) could be extracted into separate EventSubscribers.
 */
class RenderControllerSetupSubscriber implements EventSubscriberInterface
{
    /**
     * this attribute key is set by onKernelController if the request is handled by a render controller
     * (and therefore needs legacy postprocessing).
     */
    private const NEEDS_POSTPROCESSING = 'fs_needs_postprocessing';

    /**
     * @var ContainerInterface Kernel container needed to access any service,
     * instead of just the ones specified in AbstractController::getSubscribedServices
     */
    private ContainerInterface $fullServiceContainer;

    // needs to be persisted between onKernelController and onKernelResponse
    private Caching $cache;

    public function __construct(ContainerInterface $container)
    {
        $this->fullServiceContainer = $container;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => 'onKernelRequest',
            KernelEvents::CONTROLLER => 'onKernelController',
            KernelEvents::RESPONSE => 'onKernelResponse',
        ];
    }

    /**
     * This is fired before routing happens,
     * and therefore before the controller is created.
     * We use this opportunity to prepare the global $container variable
     * currently used to easily prepare common controller dependencies
     * in the `Control` and `FoodsharingController` classes.
     */
    public function onKernelRequest(RequestEvent $event)
    {
        global $container;
        $container = $this->fullServiceContainer;
    }

    /**
     * This event is fired before the controller determined by routing is called.
     * Here, we first filter based on the controller, because
     * this should only do anything for render controllers.
     * Basically, this is for all non-REST/XHR code.
     */
    public function onKernelController(ControllerEvent $event)
    {
        $controller = $event->getController();

        // when a controller class defines multiple action methods, the controller
        // is returned as [$controllerInstance, 'methodName']
        if (is_array($controller)) {
            $controller = $controller[0];
        }

        if (!$this->isRenderController($controller)) {
            return;
        }

        $request = $event->getRequest();

        // for post processing, mark this request so onKernelResponse knows it should act on the response

        $request->attributes->set(self::NEEDS_POSTPROCESSING, true);

        // The actual work this does starts here!

        /* @var Session $session */
        $session = $this->get(Session::class);
        $session->initIfCookieExists();

        // is this actually used anywhere? (prod?)
        global $g_page_cache;
        if (isset($g_page_cache) && strtolower($request->getRealMethod()) === 'get') {
            /* @var Mem $mem */
            $mem = $this->get(Mem::class);
            $this->cache = new Caching($g_page_cache, $session, $mem);
            $this->cache->lookup($request);
        }

        $translator = $this->get('translator');
        $settings = $this->get(SettingsTransactions::class);
        $lang = $settings->getLocale();
        $translator->setLocale($lang);

        error_reporting(E_ALL);

        if ($request->query->has('logout')) {
            $session->logout();
        }
    }

    public function onKernelResponse(ResponseEvent $event)
    {
        $request = $event->getRequest();

        // this attribute is set by onKernelController if the controller that handled the request is a render controller.
        // we should not do anything if this request was not for a render controller,
        // to maintain exactly the same behavior as before
        if ($request->attributes->get(self::NEEDS_POSTPROCESSING) !== true) {
            return;
        }

        $response = $event->getResponse();

        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        /** @var ContentSecurityPolicy $csp */
        $csp = $this->get(ContentSecurityPolicy::class);
        $cspString = $csp->generate($request->getSchemeAndHttpHost(), CSP_REPORT_URI, CSP_REPORT_ONLY);
        $cspParts = explode(': ', $cspString, 2);
        $response->headers->set($cspParts[0], $cspParts[1]);

        if (isset($this->cache) && $this->cache->shouldCache($request)) {
            $this->cache->cache($request, $response->getContent());
        }
    }

    private function isRenderController(object $controller): bool
    {
        return $controller instanceof FoodsharingController;
    }

    private function get($id)
    {
        return $this->fullServiceContainer->get($id);
    }
}
