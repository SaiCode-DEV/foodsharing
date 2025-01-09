<?php

namespace Foodsharing\RestApi\Models\FeatureToggle;

final readonly class FeatureToggle
{
    public string $identifier;
    public bool $isActive;

    public function __construct(string $identifier, bool $isActive)
    {
        $this->isActive = $isActive;
        $this->identifier = $identifier;
    }
}
