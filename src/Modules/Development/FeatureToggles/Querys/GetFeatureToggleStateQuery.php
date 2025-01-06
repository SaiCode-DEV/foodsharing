<?php

declare(strict_types=1);

namespace Foodsharing\Modules\Development\FeatureToggles\Querys;

use Foodsharing\Modules\Core\Database;
use Foodsharing\Modules\Core\DatabaseNoValueFoundException;

final readonly class GetFeatureToggleStateQuery
{
    public function __construct(
        private Database $database,
        private string $siteEnvironment,
    ) {
    }

    public function execute(string $featureToggleIdentifier): bool
    {
        try {
            $state = $this->database->fetchValue('SELECT is_active FROM fs_feature_toggles WHERE identifier = :featureToggleIdentifier AND site_environment = :siteEnvironment', [
                'featureToggleIdentifier' => $featureToggleIdentifier,
                'siteEnvironment' => $this->siteEnvironment,
            ]);
        } catch (DatabaseNoValueFoundException) {
            return false;
        }

        return (bool)$state;
    }
}
