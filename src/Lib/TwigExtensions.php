<?php

namespace Foodsharing\Lib;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class TwigExtensions extends AbstractExtension
{
    #[\Override]
    public function getFunctions(): array
    {
        return [
            new TwigFunction('contentMainWidth', $this->contentMainWidthFunction(...))
        ];
    }

    public function contentMainWidthFunction($hasLeft, $hasRight, $leftWidth, $rightWidth, $baseWidth = 24): int
    {
        if ($hasLeft) {
            $baseWidth -= $leftWidth;
        }
        if ($hasRight) {
            $baseWidth -= $rightWidth;
        }

        return $baseWidth;
    }
}
