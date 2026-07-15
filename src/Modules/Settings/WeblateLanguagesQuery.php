<?php

declare(strict_types=1);

namespace Foodsharing\Modules\Settings;

use Foodsharing\Modules\Core\DTO\TranslatedLocale;
use Locale;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class WeblateLanguagesQuery
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
    ) {
    }

    /**
     * Fetches all languages from Weblate whose translations have been started, or null if Weblate's API is not
     * available.
     *
     * @return ?TranslatedLocale[]
     */
    public function execute(): ?array
    {
        try {
            $response = $this->httpClient->request('GET', WEBLATE_API_URL, ['timeout' => 10]);
            if ($response->getStatusCode() >= 400) {
                return null;
            }
            $content = $response->toArray();

            return array_map(fn ($locale) => new TranslatedLocale(
                $locale['code'],
                (int)$locale['translated_percent'],
                Locale::getDisplayLanguage($locale['code'], $locale['code']),
                Locale::getDisplayLanguage($locale['code'], 'en')
            ), $content);
        } catch (\Throwable) {
        }

        return null;
    }
}
