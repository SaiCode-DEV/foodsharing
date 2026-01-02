<?php

namespace Foodsharing\Utility;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class RouteHelper
{
    public function __construct(
        private RequestStack $requestStack,
        private UrlGeneratorInterface $router,
    ) {
    }

    // For Symfony controllers: Use $this->redirect(toRoute) instead.
    public function goAndExit(string $url): never
    {
        header('Location: ' . $url);
        exit;
    }

    public function goLoginAndExit(): never
    {
        $this->goPageAndExit('login', ['ref' => $_SERVER['REQUEST_URI']]);
    }

    public function goPageAndExit(string $page = '', array $params = [], bool $pageIsSymfonyRoute = false): never
    {
        if (empty($page)) {
            if ($this->request()->query->has('bid')) {
                $params['bid'] = (int)$this->request()->query->get('bid');
            }
            $url = $this->router->generate($this->getSymfonyRoute(), $params);
        } else {
            if (!$pageIsSymfonyRoute) {
                $url = $this->router->generate('index', ['page' => $page, ...$params]);
            } else {
                $url = $this->router->generate($page, $params);
            }
        }
        $this->goAndExit($url);
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

    private function request(): Request
    {
        return $this->requestStack->getCurrentRequest();
    }
}
