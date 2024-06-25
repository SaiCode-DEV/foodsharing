<?php

namespace Foodsharing\Utility;

use Foodsharing\Entrypoint\IndexController;
use Foodsharing\Lib\Session;
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
    ) {
    }

    // For Symfony controllers: Use $this->redirect(toRoute) instead.
    public function goAndExit(string $url): never
    {
        header('Location: ' . $url);
        exit;
    }

    public function goSelfAndExit(): never
    {
        $this->goAndExit($this->getSelf());
    }

    public function goLoginAndExit(): never
    {
        $this->goPageAndExit('login', ['ref' => $this->getSelf()]);
    }

    public function goPageAndExit(string $page = '', array $params = [], bool $pageIsSymfonyRoute = false): never
    {
        if (empty($page)) {
            if ($this->request()->query->has('bid')) {
                $params['bid'] = (int)$this->request()->query->get('bid');
            }
            if ($this->isUsingLegacyController()) {
                $url = $this->router->generate('index', ['page' => $this->getPage(), ...$params]);
            } else {
                $url = $this->router->generate($this->getSymfonyRoute(), $params);
            }
        } else {
            if (!$pageIsSymfonyRoute) {
                $url = $this->router->generate('index', ['page' => $page, ...$params]);
            } else {
                $url = $this->router->generate($page, $params);
            }
        }
        $this->goAndExit($url);
    }

    public function getSelf()
    {
        return $_SERVER['REQUEST_URI'];
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

    public function isUsingLegacyController(): bool
    {
        $controller = $this->request()->attributes->get('_controller');

        return $controller === IndexController::class;
    }

    public function getLegalControlIfNecessary(): ?string
    {
        if ($this->session->mayRole() && !$this->onSettingsOrLogoutPage() && !$this->legalRequirementsMetByUser()) {
            return 'legal';
        }

        return null;
    }

    private function legalRequirementsMetByUser(): bool
    {
        return $this->usersPrivacyPolicyUpToDate() && $this->usersPrivacyNoticeUpToDate();
    }

    private function usersPrivacyPolicyUpToDate(): bool
    {
        $privacyPolicyVersion = $this->legalGateway->getPpVersion();

        return $privacyPolicyVersion && $privacyPolicyVersion == $this->session->user('privacy_policy_accepted_date');
    }

    private function usersPrivacyNoticeUpToDate(): bool
    {
        if (!$this->session->mayRole(Role::STORE_MANAGER)) {
            return true;
        }
        $privacyNoticeVersion = $this->legalGateway->getPnVersion();

        return $privacyNoticeVersion && $privacyNoticeVersion == $this->session->user('privacy_notice_accepted_date');
    }

    private function onSettingsOrLogoutPage(): bool
    {
        return in_array($this->getPage(), ['settings', 'logout']);
    }

    private function request(): Request
    {
        return $this->requestStack->getCurrentRequest();
    }
}
