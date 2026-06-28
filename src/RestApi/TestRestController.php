<?php

declare(strict_types=1);

namespace Foodsharing\RestApi;

use Foodsharing\Lib\Session;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * Test-only REST controller for E2E testing utilities.
 * Only available in test and development environments.
 */
#[OA\Tag(name: 'test')]
final class TestRestController extends AbstractFoodsharingRestController
{
    public function __construct(
        Session $session,
        private readonly CacheInterface $cache,
    ) {
        parent::__construct($session);
    }

    #[OA\Delete(
        summary: 'Clear a specific cache key.',
        description: 'Only works in test/dev environments to prevent accidental use in production.',
    )]
    #[OA\Response(response: Response::HTTP_OK, description: 'Cache cleared successfully')]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Not available in production')]
    #[Route(path: 'test/clearcache/{cacheKey}', methods: ['DELETE'])]
    public function clearCache(string $cacheKey): Response
    {
        // Only allow in test and development environments
        if (!in_array(SITE_ENVIRONMENT, ['test', 'development'])) {
            throw new AccessDeniedHttpException('Test endpoints are not available in production');
        }

        $this->cache->delete($cacheKey);

        return $this->respondOK();
    }
}
