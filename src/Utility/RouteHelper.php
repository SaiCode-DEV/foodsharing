<?php

namespace Foodsharing\Utility;

use Foodsharing\Lib\Db\Mem;
use Foodsharing\Lib\Session;
use Foodsharing\Modules\Core\DBConstants\Content\ContentId;
use Foodsharing\Modules\Core\DBConstants\Foodsaver\Role;
use Foodsharing\Modules\Legal\LegalGateway;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class RouteHelper
{
    public function __construct(
        private readonly Session $session,
        private readonly LegalGateway $legalGateway,
        private readonly RequestStack $requestStack,
        private readonly UrlGeneratorInterface $router,
        private readonly Mem $mem,
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

    public function isRedirectToLegalControlNecessary(Request $request): bool
    {
        return $this->session->mayRole() && !$this->isRouteWithoutLegalRequirement($request)
            && !($this->usersPrivacyPolicyUpToDate() && $this->usersPrivacyNoticeUpToDate());
    }

    /**
     * This is the same as isRedirectToLegalControlNecessary but without checking the route, because the API should
     * be restricted even on pages like the legal page and the profile settings page.
     */
    public function isApiRestrictedForLegalReasons(): bool
    {
        return $this->session->mayRole() && !($this->usersPrivacyPolicyUpToDate() && $this->usersPrivacyNoticeUpToDate());
    }

    private function usersPrivacyPolicyUpToDate(): bool
    {
        $privacyPolicyVersion = $this->mem->get(Mem::PRIVATE_POLICY_REDIS_KEY);

        // If for some reason the date is not in Redis, fetch it from the database and store it in Redis
        if (empty($privacyPolicyVersion)) {
            $privacyPolicyVersion = $this->legalGateway->getPpVersion();
            $this->mem->set(Mem::PRIVATE_POLICY_REDIS_KEY, $privacyPolicyVersion);
        }

        return $privacyPolicyVersion && $privacyPolicyVersion == $this->session->user('privacy_policy_accepted_date');
    }

    private function usersPrivacyNoticeUpToDate(): bool
    {
        if (!$this->session->mayRole(Role::STORE_MANAGER)) {
            return true;
        }
        $privacyNoticeVersion = $this->mem->get(Mem::PRIVATE_NOTICE_REDIS_KEY);

        // If for some reason the date is not in Redis, fetch it from the database and store it in Redis
        if (empty($privacyNoticeVersion)) {
            $privacyNoticeVersion = $this->legalGateway->getPnVersion();
            $this->mem->set(Mem::PRIVATE_NOTICE_REDIS_KEY, $privacyNoticeVersion);
        }

        return $privacyNoticeVersion && $privacyNoticeVersion == $this->session->user('privacy_notice_accepted_date');
    }

    private function isRouteWithoutLegalRequirement(Request $request): bool
    {
        $path = $request->getPathInfo();
        $method = $request->getMethod();

        $excludeUris = [
            'GET' => [
                '/legal',
                '/logout',
                '/user/current/deleteaccount',
                '/api/legal/page-data',
                '/api/content/' . ContentId::PRIVACY_NOTICE_CONTENT,
                '/api/content/' . ContentId::PRIVACY_POLICY_CONTENT,
            ],
            'DELETE' => [
                '/api/user/' . $this->session->id(),
            ],
            'PATCH' => [
                '/api/legal/acknowledge',
            ],
        ];

        return in_array($path, $excludeUris[$method] ?? [])
            || ($path === '/user/current/settings' || $path === '/user/' . $this->session->id() . '/settings')
            && $request->query->get('sub') === 'deleteaccountlegal';
    }

    private function request(): Request
    {
        return $this->requestStack->getCurrentRequest();
    }
}
