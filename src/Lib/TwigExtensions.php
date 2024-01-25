<?php

namespace Foodsharing\Lib;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class TwigExtensions extends AbstractExtension
{
    public function getFunctions()
    {
        return [
            new TwigFunction('contentMainWidth', $this->contentMainWidthFunction(...))
        ];
    }

    public function contentMainWidthFunction($hasLeft, $hasRight, $leftWidth, $rightWidth, $baseWidth = 24)
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
