<?php

declare(strict_types=1);

namespace Foodsharing\Modules\Petition\Query;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class BundestagPetitionDataQuery
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
    ) {
    }

    /**
     * Fetches the number of online signatures from the Bundestag petition page.
     * @return array containing the number of signatures and days left, or null if any problem occures
     */
    public function execute(): ?array
    {
        try {
            $response = $this->httpClient->request('GET', BUNDESTAG_PETITION_PAGE_URL);
            $htmlContent = $response->getContent();

            if (!$htmlContent) {
                return null;
            }

            $result = ['href' => BUNDESTAG_PETITION_PAGE_URL];
            // Extract the number of online signatures
            preg_match('/<span class="mzanzahl">\s*([0-9]+)\s*<\/span>/', $htmlContent, $matches);
            $result['signatures'] = intval($matches[1]);

            // Extract the number of days left
            preg_match('/<div class="progress">(?:.|\s)*?<div class="overlay">(?:.|\s)*?<span>(\d+)<\/span>/', $htmlContent, $matches);
            $result['daysLeft'] = intval($matches[1]);

            return $result;
        } catch (\Throwable $_) {
        }

        return null;
    }
}
