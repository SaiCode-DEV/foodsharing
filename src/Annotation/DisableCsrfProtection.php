<?php

namespace Foodsharing\Annotation;

/**
 * @Annotation
 * @Target({"METHOD"})
 */
#[\Attribute(\Attribute::TARGET_METHOD)]
class DisableCsrfProtection
{
}
