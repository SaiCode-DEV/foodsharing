<?php

namespace Foodsharing\Lib;

use Foodsharing\Lib\Db\Mem;
use Symfony\Component\HttpFoundation\Request;

class Caching
{
    private array $cacheRules;
    private readonly string $cacheMode;

    public function __construct(
        $cacheRules,
        private readonly Session $session,
        private readonly Mem $mem,
    ) {
        $this->cacheRules = $cacheRules;
        $this->cacheMode = $this->session->mayRole() ? 'u' : 'g';
    }

    public function lookup(Request $request): void
    {
        if ($this->shouldCache($request) && ($page = $this->mem->getPageCache($this->session->id())) !== false && !$request->query->has('flush')) {
            if ($page[0] == '{' || $page[0] == '[') {
                // just assume it's an JSON, to prevent the browser from interpreting it as
                // HTML, which could result in XSS possibilities
                /* this part goes together with /xhrapp. It is not needed anymore when it is gone. */
                header('Content-Type: application/json');
            }
            echo $page;
            exit;
        }
    }

    public function shouldCache(Request $request): bool
    {
        return isset($this->cacheRules[$request->getRequestUri()][$this->cacheMode]);
    }

    public function cache(Request $request, $responseContent): void
    {
        $this->mem->setPageCache(
            $responseContent,
            $this->cacheRules[$request->getRequestUri()][$this->cacheMode],
            $this->session->id()
        );
    }
}
