<?php

namespace Foodsharing\Utility;

/**
 * @deprecated Please use the Request object instead.
 *
 * This only exists for old, hacky controllers that rely on
 * nginx config hacks that we're trying to get rid of
 */
class UriHelper
{
    /**
     * @return false|string
     *
     * @deprecated Use the Request object instead
     */
    private function uri($index)
    {
        if (isset($_GET['uri'])) {
            $uri = explode('/', (string)$_SERVER['REQUEST_URI']);
            if (isset($uri[$index])) {
                return $uri[$index];
            }
        }

        return false;
    }

    /**
     * @deprecated Use the Request object instead
     */
    public function uriInt($index): int
    {
        return (int)$this->uri($index);
    }

    /**
     * @return array|false|string|string[]|null
     *
     * @deprecated Use the Request object instead
     */
    public function uriStr($index): array|bool|string|null
    {
        $val = $this->uri($index);
        if ($val !== false) {
            return preg_replace('/[^a-z0-9\-]/', '', $val);
        }

        return false;
    }
}
