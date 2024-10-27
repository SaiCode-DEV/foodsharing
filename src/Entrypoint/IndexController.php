<?php

namespace Foodsharing\Entrypoint;

use Foodsharing\Lib\Routing;
use Foodsharing\Modules\Core\Control;
use Foodsharing\Utility\PageHelper;
use Foodsharing\Utility\RouteHelper;
use Foodsharing\Utility\WebpackHelper;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\UrlHelper;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;

class IndexController extends AbstractController
{
    public function __invoke(
        Request $request,
        RouteHelper $routeHelper,
        PageHelper $pageHelper,
        WebpackHelper $webpackHelper,
        UrlHelper $urlHelper,
        #[Autowire(param: 'kernel.project_dir')] string $projectDir,
        #[MapQueryParameter] string $page = 'index',
    ): Response {
        $response = new Response('--');

        $page = $routeHelper->getLegalControlIfNecessary() ?? $page;

        if (Routing::isPorted($page)) {
            return $this->doPortedRedirect($page, $request, $urlHelper);
        }

        $controllerFqcn = Routing::getClassName($page);

        try {
            global $container;
            if ($controllerFqcn !== null) {
                // set up assets for this module
                $moduleName = Routing::getModuleName($page);
                if (!empty($moduleName)) {
                    $webpackHelper->prepareWebpackAssets($moduleName);
                }

                /** @var Control $controller */
                $controller = $container->get(ltrim($controllerFqcn, '\\'));
            }
        } catch (ServiceNotFoundException) {
            throw $this->createNotFoundException();
        }

        if (isset($controller)) {
            $action = $request->query->get('a');
            if ($action !== null && is_callable([$controller, $action])) {
                $controller->$action($request, $response);
            } else {
                // In practice, every class inheriting from Control has an index method,
                // but it does not exist in Control itself, which is why PHPStan complains here.
                /* @phpstan-ignore-next-line */
                $controller->index($request, $response);
            }

            if ($request->query->has('sub')) {
                $sub = $request->query->get('sub');

                if ($sub !== null && is_callable([$controller, $sub])) {
                    // this only happens if the submethod is public
                    $controller->$sub($request, $response);
                }
            }
        } else {
            throw $this->createNotFoundException();
        }

        $controllerUsedResponse = $response->getContent() !== '--';
        if (!$controllerUsedResponse) {
            $page = $this->renderView('layouts/default.twig', $pageHelper->generateAndGetGlobalViewData());

            $response->setContent($page);
        }

        return $response;
    }

    // because the highest level routing parameter is always 'page',
    // we can easily port almost all controllers without thought
    // by turning that parameter into a root level path
    // e.g. /?page=content&a=b&c=1 becomes /content?a=b&c=1
    private function doPortedRedirect(string $page, Request $request, UrlHelper $urlHelper): Response
    {
        $request->query->remove('page');

        $page = Routing::getPortedName($page);
        $newUrl = '/' . $page . '?' . http_build_query($request->query->all());

        // use 307 here because it is guaranteed not to change anything about the request otherwise
        // (could also use 308 here)
        return new RedirectResponse($urlHelper->getAbsoluteUrl($newUrl), Response::HTTP_TEMPORARY_REDIRECT);
    }
}
