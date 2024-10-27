<?php

namespace Foodsharing\Entrypoint;

use Foodsharing\Lib\Session;
use Foodsharing\Modules\Basket\BasketXhr;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;

class XhrAppController extends AbstractController
{
    public const PERMISSION_DENIED = 'permission_denied';

    /**
     * @var ContainerInterface Kernel container needed to access any service,
     * instead of just the ones specified in AbstractController::getSubscribedServices
     */
    private ContainerInterface $fullServiceContainer;

    public function __construct(ContainerInterface $container)
    {
        $this->fullServiceContainer = $container;
    }

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

        $class = match ($app) {
            'basket' => BasketXhr::class,
            default => null,
        };

        global $container;
        $container = $this->fullServiceContainer;
        /** @var BasketXhr $obj */
        $obj = $this->fullServiceContainer->get(ltrim($class, '\\'));

        if (!method_exists($obj, $meth)) {
            return new Response(null, Response::HTTP_BAD_REQUEST);
        }

        $response = new Response();

        // check CSRF Header
        if (!$session->isValidCsrfHeader()) {
            $response->setProtocolVersion('1.1');
            $response->setStatusCode(Response::HTTP_FORBIDDEN);
            $response->setContent('CSRF Failed: CSRF token missing or incorrect.');

            return $response;
        }

        // execute method
        $out = $obj->$meth($request);

        if ($out === self::PERMISSION_DENIED) {
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
