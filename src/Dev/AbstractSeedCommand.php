<?php

namespace Foodsharing\Dev;

use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Tests\Support\Helper\Foodsharing;

/**
 * Base class for both seed script (SeedCommand and TestSeedCommand) which contains common functions.
 */
abstract class AbstractSeedCommand extends Command
{
    protected function createContent(Foodsharing $I): void
    {
        $contentData = $this->loadJsonFromFile('content.json');
        foreach ($contentData as $content) {
            $I->createContent($content);
        }
    }

    protected function insertFetchWeightValues(Foodsharing $I)
    {
        $values = [
            [0, 1.5],
            [1, 2.0],
            [2, 4.0],
            [3, 7.5],
            [4, 15.0],
            [5, 25.0],
            [6, 35.0],
            [7, 45.0],
            [8, 64.0],
        ];
        foreach ($values as $value) {
            $I->haveInDatabase('fs_fetchweight', ['id' => $value[0], 'weight' => $value[1]]);
        }
    }

    /**
     * Loads JSON data from the file and returns it as an array.
     *
     * @param string $filename the file path relative to this file
     * @param bool $isAssociative if the data is supposed to be loaded as an associative array
     * @throws RuntimeException if the file does not exist or if the JSON data is invalid
     */
    protected function loadJsonFromFile(string $filename, bool $isAssociative = true): array
    {
        $file = 'src/Dev/' . $filename;
        if (!file_exists($file)) {
            throw new RuntimeException("File {$file} not found");
        }

        $data = json_decode(file_get_contents($file), $isAssociative);
        if (is_null($data)) {
            throw new RuntimeException("JSON data from file {$file} could not be decoded");
        }

        return $data;
    }
}
