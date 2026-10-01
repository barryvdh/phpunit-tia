<?php

declare(strict_types=1);

namespace JMac\Testing\PhpUnit\Tia\Integrations\Laravel;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Which files use a view, read from the source. For a view no test rendered
 * yet, such as a new partial, these lead to the tests that will: the tests
 * linked to a view that includes it, or to a class that renders it.
 *
 * The Blade walk is ported from Pest's Graph::bladeAncestorsFor(). Only
 * literal names are found: `@include('partials.total')`, `<x-alert>`,
 * `view('emails.invoice')`, `'livewire.chat'` in a component class. A name
 * built at runtime is not, so the resolver only trusts a walk that found
 * tests.
 */
final class ViewReferences
{
    private const string VIEWS = 'resources/views/';

    private const string COMPONENTS = 'resources/views/components/';

    /** @var array<string, string>|null project-relative path => source */
    private ?array $bladeSources = null;

    /** @var array<string, string>|null project-relative path => source */
    private ?array $phpSources = null;

    public function __construct(private readonly string $projectRoot) {}

    public static function isView(string $relativePath): bool
    {
        return str_starts_with($relativePath, self::VIEWS) && str_ends_with($relativePath, '.blade.php');
    }

    /**
     * @return list<string> project-relative views that use $view, directly or
     *                      through another view, and the classes in app/ that
     *                      name $view or one of those views
     */
    public function using(string $view): array
    {
        $views = [$view => true];
        $found = true;

        while ($found) {
            $found = false;

            foreach ($this->bladeSources() as $candidate => $source) {
                if (isset($views[$candidate])) {
                    continue;
                }

                foreach (array_keys($views) as $target) {
                    if ($this->bladeReferences($source, $target)) {
                        $views[$candidate] = true;
                        $found = true;

                        break;
                    }
                }
            }
        }

        $classes = [];

        foreach ($this->phpSources() as $class => $source) {
            foreach (array_keys($views) as $target) {
                $name = self::viewName($target);

                if ($name !== null && preg_match('#[\'"]'.preg_quote($name, '#').'[\'"]#', $source) === 1) {
                    $classes[] = $class;

                    break;
                }
            }
        }

        unset($views[$view]);

        return [...array_keys($views), ...$classes];
    }

    private function bladeReferences(string $source, string $target): bool
    {
        $name = self::viewName($target);

        if ($name !== null) {
            $quoted = preg_quote($name, '#');

            if (preg_match('#@(include|includeIf|includeWhen|includeUnless|includeFirst|extends|component|each)\s*\([^)]*[\'"]'.$quoted.'[\'"]#', $source) === 1) {
                return true;
            }

            if (preg_match('#\b(view|View::make)\s*\(\s*[\'"]'.$quoted.'[\'"]#', $source) === 1) {
                return true;
            }
        }

        foreach (self::componentNames($target) as $component) {
            if (preg_match('#<x-'.preg_quote($component, '#').'(?=[\s>/:])#i', $source) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * `resources/views/emails/invoice.blade.php` => `emails.invoice`
     */
    private static function viewName(string $view): ?string
    {
        if (! self::isView($view)) {
            return null;
        }

        return str_replace('/', '.', substr($view, strlen(self::VIEWS), -strlen('.blade.php')));
    }

    /**
     * The tags an anonymous component is used with: `<x-forms.text-input>` for
     * components/forms/text-input.blade.php, and `<x-card>` for
     * components/card/index.blade.php as well as `<x-card.index>`.
     *
     * @return list<string>
     */
    private static function componentNames(string $view): array
    {
        if (! str_starts_with($view, self::COMPONENTS) || ! str_ends_with($view, '.blade.php')) {
            return [];
        }

        $name = str_replace('/', '.', substr($view, strlen(self::COMPONENTS), -strlen('.blade.php')));
        $names = [$name, str_replace('_', '-', $name)];

        if (str_ends_with($name, '.index')) {
            $base = substr($name, 0, -strlen('.index'));
            $names[] = $base;
            $names[] = str_replace('_', '-', $base);
        }

        return array_values(array_unique($names));
    }

    /**
     * @return array<string, string>
     */
    private function bladeSources(): array
    {
        return $this->bladeSources ??= $this->sources(self::VIEWS, '.blade.php');
    }

    /**
     * @return array<string, string>
     */
    private function phpSources(): array
    {
        return $this->phpSources ??= $this->sources('app/', '.php');
    }

    /**
     * @return array<string, string>
     */
    private function sources(string $directory, string $suffix): array
    {
        $root = rtrim($this->projectRoot, '/').'/';

        if (! is_dir($root.$directory)) {
            return [];
        }

        $sources = [];
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root.$directory, FilesystemIterator::SKIP_DOTS),
        );

        foreach ($files as $file) {
            assert($file instanceof SplFileInfo);

            if (! $file->isFile() || ! str_ends_with($file->getPathname(), $suffix)) {
                continue;
            }

            $source = @file_get_contents($file->getPathname());

            if ($source !== false) {
                $sources[substr($file->getPathname(), strlen($root))] = $source;
            }
        }

        ksort($sources);

        return $sources;
    }
}
