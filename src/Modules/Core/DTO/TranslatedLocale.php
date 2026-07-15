<?php

namespace Foodsharing\Modules\Core\DTO;

class TranslatedLocale
{
    /**
     * ISO language code for the API.
     */
    public string $languageCode;
    /**
     * How much of the language is translated as a percentage between 0 and 100. Null if unknown.
     */
    public ?int $percentageTranslated;
    /**
     * Name of the language in its own spelling.
     */
    public string $name;
    /**
     * Name of the language in English.
     */
    public string $englishName;

    public function __construct(string $languageCode, ?int $percentageTranslated, string $name, string $englishName)
    {
        $this->languageCode = $languageCode;
        $this->percentageTranslated = $percentageTranslated;
        $this->name = $name;
        $this->englishName = $englishName;
    }
}
