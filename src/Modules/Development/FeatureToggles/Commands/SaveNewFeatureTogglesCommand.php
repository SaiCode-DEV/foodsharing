<?php

declare(strict_types=1);

namespace Foodsharing\Modules\Development\FeatureToggles\Commands;

use Foodsharing\Modules\Core\Database;

final readonly class SaveNewFeatureTogglesCommand
{
    public function __construct(
        private Database $database,
        private string $siteEnvironment,
    ) {
    }

    /**
     * Creates new database entries for these identifiers. They are not active after creation.
     *
     * @param string[] $identifiers
     */
    public function execute(array $identifiers): void
    {
        $rows = [];

        foreach ($identifiers as $identifier) {
            $rows[] = [
                'identifier' => $identifier,
                'site_environment' => $this->siteEnvironment,
                'is_active' => false,
            ];
        }

        $this->database->insertMultiple('fs_feature_toggles', $rows);
    }
}
