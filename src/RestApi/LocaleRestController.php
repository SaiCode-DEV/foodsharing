<?php

namespace Foodsharing\RestApi;

use Foodsharing\Lib\Session;
use Foodsharing\Modules\Core\DBConstants\Foodsaver\UserOptionType;
use Foodsharing\Modules\Settings\SettingsTransactions;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag(name: 'locale')]
#[OA\Response(response: Response::HTTP_UNAUTHORIZED, description: 'Not logged in.')]
class LocaleRestController extends AbstractFoodsharingRestController
{
    public function __construct(
        private readonly SettingsTransactions $settingsTransactions,
        protected Session $session,
    ) {
        parent::__construct($session);
    }

    #[OA\Get(summary: 'Returns the locale setting for the current session.')]
    #[Route('locale', methods: ['GET'])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success', content: new OA\JsonContent(properties: [
        new OA\Property(property: 'locale', type: 'string', example: 'de'),
    ]))]
    public function getLocale(): Response
    {
        $this->assertLoggedIn();

        $locale = $this->settingsTransactions->getLocale();

        return $this->respondOK(['locale' => $locale]);
    }

    #[OA\Put(summary: 'Sets the locale for the current session.')]
    #[Route('locale', methods: ['PUT'])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success', content: new OA\JsonContent(properties: [
        new OA\Property(property: 'locale', type: 'string', example: 'de', description: 'The newly set locale.'),
    ]))]
    #[OA\Response(response: Response::HTTP_UNAUTHORIZED, description: 'Not logged in.')]
    public function setLocale(#[MapQueryParameter] ?string $locale): Response
    {
        $this->assertLoggedIn();

        if (empty($locale)) {
            $locale = SettingsTransactions::DEFAULT_LOCALE;
        }
        $this->settingsTransactions->setOption(UserOptionType::LOCALE, $locale);

        return $this->getLocale();
    }
}
