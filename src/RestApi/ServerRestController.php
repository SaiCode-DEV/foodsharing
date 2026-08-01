<?php

namespace Foodsharing\RestApi;

use Foodsharing\Lib\Session;
use Foodsharing\RestApi\Models\Server\ServerDataModel;
use Foodsharing\RestApi\Models\Server\ServerRoutesModel;
use Foodsharing\Utility\PageHelper;
use Nelmio\ApiDocBundle\Annotation\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

#[OA\Tag(name: 'server')]
class ServerRestController extends AbstractFoodsharingRestController
{
    public function __construct(
        protected Session $session,
        private readonly PageHelper $pageHelper,
        private readonly RouterInterface $router,
        private readonly CacheInterface $cache,
    ) {
        parent::__construct($session);
    }

    #[OA\Get(summary: 'Returns server configuration data and user context for the frontend. This includes user data,'
        . ' permissions, environment settings, and API keys.')]
    #[Route('server/data', methods: ['GET'])]
    #[OA\Response(
        response: Response::HTTP_OK,
        description: 'Success',
        content: new OA\JsonContent(ref: new Model(type: ServerDataModel::class))
    )]
    public function getServerData(): Response
    {
        return $this->respondOK($this->pageHelper->getServerData());
    }

    #[OA\Get(
        summary: 'Returns all registered routes in the application.',
        description: 'This can be used by the frontend to dynamically generate URLs using vue-router or to synchronize
                      client-side routing with server-side routes.

                      Routes are separated into:
                      - api: API routes (paths starting with /api/)
                      - routes: Regular page/controller routes (all other routes)

                      Note: The route collection is cached since getRouteCollection() is slow.
                      - Production: 24 hours cache (routes don\'t change at runtime)
                      - Development: 10 seconds cache (for faster testing)
                      - Cache is automatically invalidated when version changes (deployment)'
    )]
    #[Route('server/routes', methods: ['GET'])]
    #[OA\Response(
        response: Response::HTTP_OK,
        description: 'Success - Returns all routes separated into API and regular routes',
        content: new OA\JsonContent(ref: new Model(type: ServerRoutesModel::class))
    )]
    public function getRoutes(): Response
    {
        $version = defined('SRC_REVISION') ? SRC_REVISION : 'DEV';
        $cacheKey = 'foodsharingServerRoutes_' . $version;

        $routeData = $this->cache->get($cacheKey, function (ItemInterface $cacheItem) use ($version) {
            $cacheItem->expiresAfter(ROUTES_CACHE_DURATION);

            $routeCollection = $this->router->getRouteCollection();
            $apiRoutes = [];
            $regularRoutes = [];

            foreach ($routeCollection as $name => $route) {
                // Filter out internal Symfony routes (those starting with _)
                if (str_starts_with($name, '_')) {
                    continue;
                }

                $routeData = [
                    'name' => $name,
                    'path' => $route->getPath(),
                    'methods' => $route->getMethods() ?: ['GET'],
                    'parameters' => array_keys($route->getRequirements()),
                ];

                // Separate API routes from regular routes
                if (str_starts_with($route->getPath(), '/api/')) {
                    $apiRoutes[] = $routeData;
                } else {
                    $regularRoutes[] = $routeData;
                }
            }

            return [
                'version' => $version,
                'api' => $apiRoutes,
                'routes' => $regularRoutes,
            ];
        });

        return $this->respondOK($routeData);
    }
}
