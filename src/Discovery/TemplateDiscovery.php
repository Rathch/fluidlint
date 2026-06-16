<?php

declare(strict_types=1);

namespace Cru\Fluidlint\Discovery;

use Cru\Fluidlint\Configuration\Configuration;
use Symfony\Component\Finder\Finder;

final class TemplateDiscovery
{
    public function __construct(
        private readonly Configuration $configuration,
    ) {
    }

    /**
     * @param list<string> $inputPaths
     * @return list<string> Absolute file paths
     */
    public function discover(array $inputPaths): array
    {
        $files = [];
        $roots = $inputPaths !== [] ? $inputPaths : [getcwd() ?: '.'];

        foreach ($roots as $root) {
            $absoluteRoot = realpath($root) ?: $root;
            if (is_file($absoluteRoot) && $this->isTemplateFile($absoluteRoot)) {
                $files[] = $absoluteRoot;
                continue;
            }

            if (!is_dir($absoluteRoot)) {
                continue;
            }

            $finder = Finder::create()
                ->files()
                ->in($absoluteRoot)
                ->name('*.html')
                ->name('*.fluid.html');

            foreach ($finder as $file) {
                $path = $file->getRealPath() ?: $file->getPathname();
                if (!$this->isTemplatePath($path) || $this->shouldExclude($path)) {
                    continue;
                }
                $files[] = $path;
            }
        }

        $files = array_values(array_unique($files));
        sort($files);

        return $files;
    }

    private function isTemplateFile(string $path): bool
    {
        return $this->isTemplatePath($path);
    }

    private function isTemplatePath(string $path): bool
    {
        if (!preg_match('/\.(fluid\.)?html$/', $path)) {
            return false;
        }

        if (str_contains($path, '/Resources/Private/')) {
            return true;
        }

        return str_contains($path, '/Templates/')
            || str_contains($path, '/Partials/')
            || str_contains($path, '/Layouts/');
    }

    private function shouldExclude(string $path): bool
    {
        foreach ($this->configuration->exclude as $excludePattern) {
            if (fnmatch($excludePattern, $path) || fnmatch($excludePattern, basename($path))) {
                return true;
            }
            if (str_contains($path, str_replace('**/', '', rtrim($excludePattern, '/**')))) {
                return true;
            }
        }

        if (!$this->configuration->includeSystemExtensions) {
            if (str_contains($path, '/typo3/sysext/') || str_contains($path, '/public/typo3/')) {
                return true;
            }
        }

        return false;
    }
}
