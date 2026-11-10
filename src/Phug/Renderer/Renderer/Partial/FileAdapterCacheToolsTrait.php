<?php

namespace Phug\Renderer\Partial;

use Phug\CompilerInterface;
use Phug\Renderer;

trait FileAdapterCacheToolsTrait
{
    /**
     * Write the cache file with the given contents and map of imports.
     *
     * @param string $destination
     * @param string $output
     * @param array  $importsMap
     *
     * @return bool
     */
    protected function cacheFileContents($destination, $output, $importsMap = [])
    {
        $imports = $this->writeFileByRename(
            $destination.'.imports.serialize.txt',
            serialize($importsMap)
        ) ?: 0;
        $template = $this->writeFileByRename($destination, $output);

        return $template && $imports;
    }

    /**
     * Write $contents to a temporary file in the directory of $path, then
     * rename it to $path. rename() within one directory is atomic, so a
     * process that reads $path while another writes it reads the previous
     * file or the new one, never a partly written one. The file gets the mode
     * file_put_contents() would give a new file (0666 minus the umask).
     *
     * @param string $path
     * @param string $contents
     *
     * @return int|false bytes written, or false on failure
     */
    private function writeFileByRename($path, $contents)
    {
        $temporary = @tempnam(dirname($path), basename($path).'.tmp.');
        if ($temporary === false) {
            return false;
        }
        // tempnam() falls back to the system temporary directory when the
        // given one is not writable; a rename() from there would copy across
        // file systems, which is not atomic.
        if (realpath(dirname($temporary)) !== realpath(dirname($path))) {
            @unlink($temporary);

            return false;
        }
        $bytes = file_put_contents($temporary, $contents);
        if ($bytes === false
            || !chmod($temporary, 0666 & ~umask())
            || !rename($temporary, $path)
        ) {
            @unlink($temporary);

            return false;
        }

        return $bytes;
    }

    /**
     * @param Renderer $renderer
     * @param array    $events
     *
     * @throws \Phug\RendererException
     *
     * @return \Phug\CompilerInterface
     */
    protected function reInitCompiler(Renderer $renderer, array $events)
    {
        $renderer->initCompiler();
        $compiler = $renderer->getCompiler();
        $compiler->mergeEventListeners($events);

        return $compiler;
    }

    /**
     * Return directories list from a directory string as it is allowed in CLI:
     * - directory1/x/y
     * - [directory1/x/y,directory2/z]
     * as an array of strings.
     *
     * @param string $directory
     *
     * @return string[]
     */
    protected function parseCliDirectoriesInput($directory)
    {
        return array_filter(
            preg_match('/^\[(.*)]$/', $directory, $match)
                ? explode(',', $match[1])
                : [$directory],
            'strlen'
        );
    }

    /**
     * Compile a file with a given compiler and cache it.
     *
     * @param CompilerInterface $compiler
     * @param string            $path
     * @param string            $inputFile
     *
     * @return bool
     */
    protected function compileAndCache(CompilerInterface $compiler, $path, $inputFile)
    {
        return $this->cacheFileContents($path, $compiler->compileFile($inputFile), $compiler->getCurrentImportPaths());
    }

    /**
     * Compile a file with a given compiler and cache it.
     *
     * @param CompilerInterface $compiler
     * @param string            $path
     * @param string            $directory
     *
     * @return string
     */
    protected function normalizePath(CompilerInterface $compiler, $path, $directory)
    {
        $path = substr($path, strlen($directory) + 1);

        return method_exists($compiler, 'normalizePath') ? $compiler->normalizePath($path) : $path;
    }
}
