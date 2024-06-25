<?php

declare(strict_types=1);

use Rector\CodeQuality\Rector\Class_\InlineConstructorDefaultToPropertyRector;
use Rector\Config\RectorConfig;
use Rector\Php80\Rector\Class_\ClassPropertyAssignToConstructorPromotionRector;
use Rector\PHPUnit\Set\PHPUnitSetList;
use Rector\Symfony\Set\SymfonySetList;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ])
    ->withSkip([
        __DIR__ . '/tests/_support/_generated',
    ])
    ->withImportNames(true, true, false)
    ->withRules([InlineConstructorDefaultToPropertyRector::class])
    // todo create and specify kernel/container php according to
    //  https://github.com/rectorphp/rector-symfony/blob/main/README.md#provide-symfony-php-container
    ->withSymfonyContainerXml(__DIR__ . '/var/cache/dev/Foodsharing_KernelDevDebugContainer.xml')
    ->withPhpSets() // autoconfigured based on version set in composer.json
    ->withAttributesSets()
    ->withSets([
        SymfonySetList::SYMFONY_54,
        SymfonySetList::SYMFONY_60,
        SymfonySetList::SYMFONY_61,
        SymfonySetList::SYMFONY_62,
        SymfonySetList::SYMFONY_63,
        SymfonySetList::SYMFONY_64,

        SymfonySetList::SYMFONY_CODE_QUALITY,
        SymfonySetList::SYMFONY_CONSTRUCTOR_INJECTION,

        PHPUnitSetList::PHPUNIT_100,
    ])
    // skip promoting properties until PHP_CS_Fixer can format them on multiple lines
    // https://github.com/PHP-CS-Fixer/PHP-CS-Fixer/issues/6325
    // or if we add this extra rule
    // https://github.com/kubawerlos/php-cs-fixer-custom-fixers#multilinepromotedpropertiesfixer
    ->withSkip([
        ClassPropertyAssignToConstructorPromotionRector::class,
    ]);
