<?php

declare(strict_types=1);

namespace Foodsharing\Dev;

use Nelmio\ApiDocBundle\Describer\DescriberInterface;
use OpenApi\Annotations\OpenApi;
use OpenApi\Annotations\Operation;
use Symfony\Component\Routing\RouterInterface;

final class OperationIdDescriber implements DescriberInterface
{
    public function __construct(
        private readonly RouterInterface $router,
    ) {
    }

    public function describe(OpenApi $api): void
    {
        $routes = []; // mapping of controller method name to paths
        foreach ($this->router->getRouteCollection() as $routeName => $route) {
            $controller = $route->getDefault('_controller');
            if (is_string($controller) && str_contains($controller, '::')) {
                $routes[$routeName] = explode('::', $controller, 2)[1];
            }
        }

        foreach ($api->paths as $pathItem) {
            foreach (['get', 'post', 'put', 'patch', 'delete'] as $httpMethod) {
                $operation = $pathItem->{$httpMethod} ?? null;
                if (!$operation instanceof Operation) {
                    continue;
                }

                // Find matching route to assign method name as operation ID
                foreach ($routes as $routeName => $methodName) {
                    if ($operation->operationId === $httpMethod . '_' . $routeName) {
                        $operation->operationId = $methodName;
                        break;
                    }
                }
            }
        }
    }
}
