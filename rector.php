<?php

declare(strict_types=1);

use Rector\CodeQuality\Rector\Class_\InlineConstructorDefaultToPropertyRector;
use Rector\Config\RectorConfig;
use Rector\Php80\Rector\Class_\ClassPropertyAssignToConstructorPromotionRector;
use Rector\Set\ValueObject\LevelSetList;
use Rector\Symfony\Set\FOSRestSetList;
use Rector\Symfony\Set\JMSSetList;
use Rector\Symfony\Set\SensiolabsSetList;
use Rector\Symfony\Set\SymfonySetList;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->paths([
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ]);

    $rectorConfig->skip([
        __DIR__ . '/tests/_support/_generated',
    ]);

    $rectorConfig->importNames();
    $rectorConfig->importShortClasses(false);

    $rectorConfig->rule(InlineConstructorDefaultToPropertyRector::class);

    $rectorConfig->symfonyContainerXml(__DIR__ . '/var/cache/dev/Foodsharing_KernelDevDebugContainer.xml');
    // todo create and specify kernel/container php according to
    //  https://github.com/rectorphp/rector-symfony/blob/main/README.md#provide-symfony-php-container

    $rectorConfig->sets([
        SymfonySetList::SYMFONY_54,
        SymfonySetList::SYMFONY_60,
        SymfonySetList::SYMFONY_61,
        SymfonySetList::SYMFONY_62,
        SymfonySetList::SYMFONY_63,
        SymfonySetList::SYMFONY_64,

        SymfonySetList::SYMFONY_CODE_QUALITY,
        SymfonySetList::SYMFONY_CONSTRUCTOR_INJECTION,

        SymfonySetList::ANNOTATIONS_TO_ATTRIBUTES,
        FOSRestSetList::ANNOTATIONS_TO_ATTRIBUTES,
        JMSSetList::ANNOTATIONS_TO_ATTRIBUTES,
        SensiolabsSetList::ANNOTATIONS_TO_ATTRIBUTES,

        LevelSetList::UP_TO_PHP_80,
    ]);

    // skip promoting properties until PHP_CS_Fixer can format them on multiple lines
    // https://github.com/PHP-CS-Fixer/PHP-CS-Fixer/issues/6325
    // or if we add this extra rule
    // https://github.com/kubawerlos/php-cs-fixer-custom-fixers#multilinepromotedpropertiesfixer
    $rectorConfig->skip([
        ClassPropertyAssignToConstructorPromotionRector::class,
    ]);
};
