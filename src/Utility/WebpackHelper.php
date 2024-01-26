<?php

namespace Foodsharing\Utility;

class WebpackHelper
{
    public function __construct(
        private readonly PageHelper $pageHelper // shared, same instance as other references
    ) {
    }

    public function prepareWebpackAssets(string $projectDir, string $moduleName): void
    {
        $webpackModules = $projectDir . '/assets/modules.json';
        $manifest = json_decode(file_get_contents($webpackModules), true);
        $entry = 'Modules/' . $moduleName;
        if (isset($manifest[$entry])) {
            foreach ($manifest[$entry] as $asset) {
                if (str_ends_with((string)$asset, '.js')) {
                    $this->pageHelper->addWebpackScript($asset);
                } elseif (str_ends_with((string)$asset, '.css')) {
                    $this->pageHelper->addWebpackStylesheet($asset);
                }
            }
        }
    }
}
