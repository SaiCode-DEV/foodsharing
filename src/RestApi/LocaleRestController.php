<?php

namespace Foodsharing\RestApi;

use Foodsharing\Lib\Session;
use Foodsharing\Modules\Core\DBConstants\Foodsaver\UserOptionType;
use Foodsharing\Modules\Settings\SettingsTransactions;
use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\Request\ParamFetcher;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;

#[OA\Tag(name: 'locale')]
class LocaleRestController extends AbstractFoodsharingRestController
{
    public function __construct(
        private readonly SettingsTransactions $settingsTransactions,
        protected Session $session
    ) {
        parent::__construct($session);
    }

    #[Rest\Get('locale')]
    #[OA\Get(summary: 'Returns the locale setting for the current session.')]
    #[OA\Response(response: Response::HTTP_OK, description: 'Successful')]
    #[OA\Response(response: Response::HTTP_UNAUTHORIZED, description: 'Not logged in.')]
    public function getLocale(): Response
    {
        $this->assertLoggedIn();

        $locale = $this->settingsTransactions->getLocale();

        return $this->respondOK(['locale' => $locale]);
    }

    #[Rest\Post('locale')]
    #[Rest\RequestParam(name: 'locale')]
    #[OA\Post(summary: 'Sets the locale for the current session.')]
    #[OA\Response(response: Response::HTTP_OK, description: 'Successful')]
    #[OA\Response(response: Response::HTTP_UNAUTHORIZED, description: 'Not logged in.')]
    public function setLocale(ParamFetcher $paramFetcher): Response
    {
        $this->assertLoggedIn();

        $locale = $paramFetcher->get('locale');
        if (empty($locale)) {
            $locale = SettingsTransactions::DEFAULT_LOCALE;
        }
        $this->settingsTransactions->setOption(UserOptionType::LOCALE, $locale);

        return $this->getLocale();
    }
}
