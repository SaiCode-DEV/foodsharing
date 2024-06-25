<?php

namespace Foodsharing\RestApi;

use Foodsharing\Lib\Session;
use Foodsharing\Modules\Core\DBConstants\Foodsaver\UserOptionType;
use Foodsharing\Modules\Settings\SettingsTransactions;
use FOS\RestBundle\Controller\AbstractFOSRestController;
use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\Request\ParamFetcher;
use OpenApi\Annotations as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

class LocaleRestController extends AbstractFOSRestController
{
    public function __construct(
        private readonly SettingsTransactions $settingsTransactions,
        private readonly Session $session
    ) {
    }

    /**
     * Returns the locale setting for the current session.
     *
     * @OA\Tag(name="locale")
     */
    #[Rest\Get('locale')]
    public function getLocale(): Response
    {
        if (!$this->session->mayRole()) {
            throw new UnauthorizedHttpException('');
        }

        $locale = $this->settingsTransactions->getLocale();

        return $this->handleView($this->view(['locale' => $locale], 200));
    }

    /**
     * Sets the locale for the current session.
     *
     * @OA\Tag(name="locale")
     */
    #[Rest\Post('locale')]
    #[Rest\RequestParam(name: 'locale')]
    public function setLocale(ParamFetcher $paramFetcher): Response
    {
        if (!$this->session->mayRole()) {
            throw new UnauthorizedHttpException('');
        }

        $locale = $paramFetcher->get('locale');
        if (empty($locale)) {
            $locale = SettingsTransactions::DEFAULT_LOCALE;
        }
        $this->settingsTransactions->setOption(UserOptionType::LOCALE, $locale);

        return $this->getLocale();
    }
}
