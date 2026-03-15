<?php

namespace Foodsharing\Utility;

use Foodsharing\Modules\Error\ErrorController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class WebpackHelper
{
    private const CONTROLLER_SUFFIX = 'Controller';

    private string $modulesPath;
    private array $preparedModules = [];
    private bool $finalized = false;

    public function __construct(
        private readonly PageHelper $pageHelper, // shared, same instance as other references
        #[Autowire(param: 'kernel.project_dir')]
        private readonly string $projectDir,
    ) {
        $this->modulesPath = $this->projectDir . '/assets/modules.json';
        if (!is_file($this->modulesPath)) {
            $this->pageHelper->render('pages/webpack_generating.twig');

            return;
        }
    }

    public function prepareWebpackAssets(string $controllerClass): void
    {
        if ($controllerClass === ErrorController::class) {
            // Remove assets from previous controllers, so they do not interfere with the error page.
            // This also silences the error about preparing multiple modules in one request, which can happen when an error occurs.
            $this->preparedModules = [];
        }

        if (!str_ends_with($controllerClass, self::CONTROLLER_SUFFIX)) {
            throw new \InvalidArgumentException('Name of controller "' . $controllerClass . '" does not end with "' . self::CONTROLLER_SUFFIX . '".');
        }
        $className = preg_replace('/.*\\\/', '', $controllerClass);
        $moduleName = substr($className, 0, -strlen(self::CONTROLLER_SUFFIX));

        if (count($this->preparedModules) > 0) {
            trigger_error('Preparing multiple webpack modules in one request.'
                . ' This can lead to duplicate runtimes and unexpected behavior in the browser.'
                . ' Preparing module: ' . $moduleName . '.'
                . ' Already prepared: ' . implode(', ', $this->preparedModules) . '.',
                E_USER_WARNING,
            );
        }

        $this->preparedModules[] = $moduleName;
    }

    public function finalizeWebpackAssets(): void
    {
        if ($this->finalized) {
            trigger_error('Webpack assets have already been finalized. This should only be done once per request.', E_USER_WARNING);

            return;
        }

        $this->finalized = true;

        if (empty($this->preparedModules)) {
            return;
        }

        $manifest = json_decode(file_get_contents($this->modulesPath), true);

        for ($i = 0; $i < count($this->preparedModules); ++$i) {
            $this->addAssetsForModule($this->preparedModules[$i], $manifest);
        }
    }

    private function addAssetsForModule(string $moduleName, array $manifest): void
    {
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
