<?php

namespace Foodsharing\RestApi;

use Foodsharing\Lib\Session;
use Foodsharing\Modules\Core\DBConstants\Foodsaver\UserOptionType;
use Foodsharing\Modules\Core\DTO\TranslatedLocale;
use Foodsharing\Modules\Settings\SettingsTransactions;
use Foodsharing\Modules\Settings\WeblateLanguagesQuery;
use Locale;
use Nelmio\ApiDocBundle\Annotation\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

#[OA\Tag(name: 'locale')]
#[OA\Response(response: Response::HTTP_UNAUTHORIZED, description: 'Not logged in.')]
class LocaleRestController extends AbstractFoodsharingRestController
{
    private const int WEBLATE_LANGUAGES_CACHE_TIME = 60 * 60 * 24; // 24 hours

    public function __construct(
        private readonly SettingsTransactions $settingsTransactions,
        private readonly CacheInterface $cache,
        private readonly WeblateLanguagesQuery $query,
        protected Session $session,
        #[Autowire(param: 'kernel.project_dir')]
        private readonly string $projectDir,
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
    public function setLocale(#[MapQueryParameter] ?string $locale): Response
    {
        $this->assertLoggedIn();

        if (empty($locale)) {
            $locale = SettingsTransactions::DEFAULT_LOCALE;
        }
        $this->settingsTransactions->setOption(UserOptionType::LOCALE, $locale);

        return $this->respondOK();
    }

    #[OA\Get(summary: 'Returns all available languages')]
    #[Route('locales', methods: ['GET'])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success', content: new OA\JsonContent(
        description: 'The list of available language settings.',
        type: 'array',
        items: new OA\Items(ref: new Model(type: TranslatedLocale::class))
    ))]
    public function getLocales(): Response
    {
        // SRC_REVISION in the key makes sure that the old translation status does not stay in the cache after a release
        $cacheKey = 'foodsharingLanguages' . (defined('SRC_REVISION') ? SRC_REVISION : 'DEV');
        $languages = $this->cache->get($cacheKey, function (ItemInterface $cacheItem) {
            $cacheItem->expiresAfter(self::WEBLATE_LANGUAGES_CACHE_TIME);

            // Try to fetch from Weblate. If it is not available, fall back to using the filenames.
            return $this->query->execute() ?? $this->listLocalesFromFiles();
        });

        return $this->respondOK($languages);
    }

    /**
     * Finds all files of the pattern /translations/messages.*.yml and returns TranslatedLocale objects based on the
     * filenames.
     *
     * @return TranslatedLocale[]
     */
    private function listLocalesFromFiles(): array
    {
        // Find all matching files in the translations directory
        $files = scandir($this->projectDir . '/translations');
        $files = array_filter($files, fn ($f) => is_file($this->projectDir . '/translations/' . $f)
            && fnmatch('messages.*.yml', $f));
        // Extract the language codes from the filenames
        $languageCodes = array_map(fn ($f) => substr(substr($f, 9), 0, -4), $files);

        return array_map(fn ($code) => new TranslatedLocale(
            $code,
            null,
            Locale::getDisplayName($code, 'de'),
            Locale::getDisplayName($code, 'en'),
        ), $languageCodes);
    }
}
