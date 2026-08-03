<?php

namespace Foodsharing\Utility;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

final readonly class RouteHelper
{
    public function __construct(
        private RequestStack $requestStack,
    ) {
    }

    public function getSymfonyRoute(): string
    {
        return $this->request()->attributes->get('_route');
    }

    public function getPage(): string
    {
        return $this->request()->query->get('page', 'index');
    }

    public function getSubPage(): string
    {
        return $this->request()->query->get('sub', 'index');
    }

    public function autolink(string $str, array $attributes = []): string
    {
        $attributes['target'] = '_blank';
        $attrs = '';
        foreach ($attributes as $attribute => $value) {
            $attrs .= " {$attribute}=\"{$value}\"";
        }
        $str = ' ' . $str;
        $str = preg_replace(
            '`([^"=\'>])(((http|https|ftp)://|www.)[^\s<]+[^\s<\.)])`i',
            '$1<a href="$2"' . $attrs . '>$2</a>',
            $str
        ) ?: '';
        $str = substr($str, 1);

        // adds http:// if not existing
        return preg_replace('`href=\"www`', 'href="http://www', $str) ?: '';
    }

    public function request(): Request
    {
        return $this->requestStack->getCurrentRequest();
    }
}
