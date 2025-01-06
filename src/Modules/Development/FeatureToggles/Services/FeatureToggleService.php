<?php

declare(strict_types=1);

namespace Foodsharing\Modules\Development\FeatureToggles\Services;

use Foodsharing\Modules\Development\FeatureToggles\Commands\DeleteUndefinedFeatureTogglesCommand;
use Foodsharing\Modules\Development\FeatureToggles\Commands\SaveNewFeatureTogglesCommand;
use Foodsharing\Modules\Development\FeatureToggles\Commands\UpdateFeatureToggleStateCommand;
use Foodsharing\Modules\Development\FeatureToggles\DependencyInjection\FeatureToggleChecker;
use Foodsharing\Modules\Development\FeatureToggles\Enums\FeatureToggleDefinitions;
use Foodsharing\Modules\Development\FeatureToggles\Exceptions\FeatureToggleNotDefinedException;
use Foodsharing\Modules\Development\FeatureToggles\Querys\GetExistingFeatureToggles;
use Foodsharing\Modules\Development\FeatureToggles\Querys\IsFeatureToggleActiveQuery;

final readonly class FeatureToggleService implements FeatureToggleChecker
{
    public function __construct(
        private IsFeatureToggleActiveQuery $isFeatureToggleActiveQuery,
        private GetExistingFeatureToggles $existingFeatureTogglesFromDatabaseQuery,
        private SaveNewFeatureTogglesCommand $saveNewFeatureTogglesCommand,
        private DeleteUndefinedFeatureTogglesCommand $deleteUndefinedFeatureTogglesCommand,
        private UpdateFeatureToggleStateCommand $updateFeatureToggleStateCommand,
    ) {
    }

    /**
     * @throws FeatureToggleNotDefinedException
     */
    public function isFeatureToggleActive(string $identifier): bool
    {
        if (!$this->isFeatureToggleDefined($identifier)) {
            throw new FeatureToggleNotDefinedException("FeatureToggle (identifier: $identifier) is not defined");
        }

        return $this->isFeatureToggleActiveQuery->execute($identifier);
    }

    public function isFeatureToggleDefined(string $identifier): bool
    {
        return in_array($identifier, FeatureToggleDefinitions::all());
    }

    public function updateFeatureToggles(): void
    {
        $definedFeatureToggles = FeatureToggleDefinitions::all();
        $existingFeatureToggles = $this->existingFeatureTogglesFromDatabaseQuery->execute();

        $newFeatureToggles = array_diff($definedFeatureToggles, $existingFeatureToggles);
        $notDefinedFeatureToggles = array_diff($existingFeatureToggles, $definedFeatureToggles);

        $this->saveNewFeatureTogglesCommand->execute($newFeatureToggles);
        $this->deleteUndefinedFeatureTogglesCommand->execute($notDefinedFeatureToggles);
    }

    public function updateFeatureToggleState(string $identifier, bool $newState): void
    {
        $this->updateFeatureToggleStateCommand->execute($identifier, $newState);
    }
}
