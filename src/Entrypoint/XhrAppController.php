<?php

namespace Foodsharing\Entrypoint;

use Foodsharing\Lib\Routing;
use Foodsharing\Lib\Session;
use Foodsharing\Lib\Xhr\XhrResponses;
use Foodsharing\Modules\Core\Control;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;

class XhrAppController extends AbstractController
{
    /**
     * @var ContainerInterface Kernel container needed to access any service,
     * instead of just the ones specified in AbstractController::getSubscribedServices
     */
    private ContainerInterface $fullServiceContainer;

    public function __construct(ContainerInterface $container)
    {
        $this->fullServiceContainer = $container;
    }

    /*
        methods wich are excluded from the CSRF Protection.
        We start with every method and remove one by another
        NEVER ADD SOMETING TO THIS LIST!
    */
    private const csrf_whitelist = [
        'TeamXhr::contact',
        'WallPostXhr::attachimage',
    ];

    public function __invoke(
        Request $request,
        Session $session,
        #[MapQueryParameter] ?string $app,
        #[MapQueryParameter] ?string $m,
    ): Response {
        if ($app === null || $m === null) {
            return new Response(null, Response::HTTP_BAD_REQUEST);
        }

        $app = str_replace('/', '', $app);
        $meth = str_replace('/', '', $m);

        $session->initIfCookieExists();

        $class = Routing::getClassName($app, 'Xhr');

        global $container;
        $container = $this->fullServiceContainer;
        /** @var Control $obj */
        $obj = $this->fullServiceContainer->get(ltrim($class, '\\'));

        if (!method_exists($obj, $meth)) {
            return new Response(null, Response::HTTP_BAD_REQUEST);
        }

        $obj->setRequest($request);

        $response = new Response();

        // check CSRF Header
        $whitelist_key = explode('\\', $class)[3] . '::' . $meth;
        if (!in_array($whitelist_key, XhrAppController::csrf_whitelist) && !$session->isValidCsrfHeader()) {
            $response->setProtocolVersion('1.1');
            $response->setStatusCode(Response::HTTP_FORBIDDEN);
            $response->setContent('CSRF Failed: CSRF token missing or incorrect.');

            return $response;
        }

        // execute method
        $out = $obj->$meth($request);

        if ($out === XhrResponses::PERMISSION_DENIED) {
            $response->setProtocolVersion('1.1');
            $response->setStatusCode(Response::HTTP_FORBIDDEN);

            return $response;
        }

        if (!isset($out['script'])) {
            $out['script'] = '';
        }

        $out['script'] = '$(".tooltip").tooltip({show: false,hide:false,position: {	my: "center bottom-20",	at: "center top",using: function( position, feedback ) {	$( this ).css( position );	$("<div>").addClass( "arrow" ).addClass( feedback.vertical ).addClass( feedback.horizontal ).appendTo( this );}}});' . $out['script'];

        $response->headers->set('Content-Type', 'application/json');
        $response->setContent(json_encode($out));

        return $response;
    }
}
