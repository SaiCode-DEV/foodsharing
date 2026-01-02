<?php

namespace Foodsharing\RestApi\Models\FeatureToggle;

final readonly class FeatureToggle
{
    public function __construct(
        public string $identifier,
        public bool $isActive
    ) {
    }
}
