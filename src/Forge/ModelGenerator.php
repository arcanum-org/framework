<?php

declare(strict_types=1);

namespace Arcanum\Forge;

use Arcanum\Parchment\FileSystem;
use Arcanum\Parchment\Reader;
use Arcanum\Parchment\Writer;
use Arcanum\Shodo\TemplateCompiler;
use Arcanum\Toolkit\Strings;

/**
 * Generates typed Model classes from SQL files.
 *
 * Scans a domain's Model/ directory for .sql files and produces a PHP
 * class that extends Forge\Model with a typed method for each file.
 * This is the engine behind the forge:models Rune command and dev-mode
 * auto-regeneration.
 *
 * Uses stub templates (model.stub and model_method.stub) which can be
 * overridden by the app at {rootDirectory}/stubs/.
 */
final class ModelGenerator
{
    private const string STUBS_DIR = __DIR__ . '/../Rune/Command/stubs';

    public function __construct(
        private readonly string $rootDirectory = '',
        private readonly Reader $reader = new Reader(),
        private readonly Writer $writer = new Writer(),
        private readonly FileSystem $fileSystem = new FileSystem(),
        private readonly TemplateCompiler $compiler = new TemplateCompiler(),
    ) {
    }

    /**
     * Generate a model class for a single domain's Model/ directory.
     *
     * @param string $modelDir Absolute path to the Model/ directory containing .sql files.
     * @param string $classNamespace The fully qualified class name for the generated class.
     * @return string The generated PHP source code.
     */
    public function generate(string $modelDir, string $classNamespace): string
    {
        $sqlFiles = $this->discoverSqlFiles($modelDir);

        $methods = [];
        foreach ($sqlFiles as $file) {
            $methods[] = $this->renderMethod($file);
        }

        return $this->renderClass($classNamespace, $methods);
    }

    /**
     * Generate and write the model class file.
     *
     * @return bool True if written, false if no SQL files found.
     */
    public function generateAndWrite(
        string $modelDir,
        string $classNamespace,
        string $outputPath,
    ): bool {
        $sqlFiles = $this->discoverSqlFiles($modelDir);

        if ($sqlFiles === []) {
            return false;
        }

        $source = $this->generate($modelDir, $classNamespace);
        $this->writer->write($outputPath, $source);

        return true;
    }

    /**
     * Check if any SQL file in the directory is newer than the generated class.
     */
    public function isStale(string $modelDir, string $classFile): bool
    {
        if (!is_file($classFile)) {
            return true;
        }

        $classTime = filemtime($classFile);
        if ($classTime === false) {
            return true;
        }

        $sqlFiles = $this->discoverSqlFiles($modelDir);

        foreach ($sqlFiles as $file) {
            $sqlTime = filemtime($file);
            if ($sqlTime !== false && $sqlTime > $classTime) {
                return true;
            }
        }

        return false;
    }

    /**
     * Generate a sub-model class for a subdirectory within Model/.
     *
     * Uses the sub_model stub which includes a __DIR__ constructor,
     * making the generated class autowireable by Codex.
     *
     * @param string $subModelDir Absolute path to the sub-model directory.
     * @param string $classNamespace Fully qualified class name for the generated class.
     * @return string The generated PHP source code.
     */
    public function generateSubModel(string $subModelDir, string $classNamespace): string
    {
        $sqlFiles = $this->discoverSqlFiles($subModelDir);

        $methods = [];
        foreach ($sqlFiles as $file) {
            $methods[] = $this->renderMethod($file);
        }

        return $this->renderClass($classNamespace, $methods, 'sub_model');
    }

    /**
     * Generate and write a sub-model class file.
     *
     * @return bool True if written, false if no SQL files found.
     */
    public function generateAndWriteSubModel(
        string $subModelDir,
        string $classNamespace,
        string $outputPath,
    ): bool {
        $sqlFiles = $this->discoverSqlFiles($subModelDir);

        if ($sqlFiles === []) {
            return false;
        }

        $source = $this->generateSubModel($subModelDir, $classNamespace);
        $this->writer->write($outputPath, $source);

        return true;
    }

    /**
     * Discover root-level .sql files (not in subdirectories).
     *
     * @return list<string> Sorted absolute paths to .sql files.
     */
    private function discoverSqlFiles(string $dir): array
    {
        if (!is_dir($dir)) {
            return [];
        }

        $files = glob($dir . DIRECTORY_SEPARATOR . '*.sql') ?: [];
        sort($files);

        return $files;
    }

    /**
     * Discover subdirectories that contain .sql files (sub-model candidates).
     *
     * @return array<string, string> Directory name => absolute path.
     */
    public function discoverSubModelDirs(string $modelDir): array
    {
        if (!is_dir($modelDir)) {
            return [];
        }

        $dirs = [];
        $entries = scandir($modelDir) ?: [];

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $modelDir . DIRECTORY_SEPARATOR . $entry;

            if (!is_dir($path)) {
                continue;
            }

            $sqlFiles = glob($path . DIRECTORY_SEPARATOR . '*.sql') ?: [];

            if ($sqlFiles !== []) {
                $dirs[$entry] = $path;
            }
        }

        ksort($dirs);

        return $dirs;
    }

    private function renderMethod(string $sqlFile): string
    {
        $filename = basename($sqlFile, '.sql');
        $methodName = lcfirst($filename);
        $sql = $this->reader->read($sqlFile);

        $paramAnnotations = Sql::parseParams($sql);
        $bindings = Sql::extractBindings($sql);

        $params = $this->buildParamSignature($bindings, $paramAnnotations);
        $paramsArray = $this->buildParamsArray($bindings);

        $stub = $this->loadStub('model_method');

        return $this->compiler->render($stub, [
            'methodName' => $methodName,
            'params' => $params,
            'paramsArray' => $paramsArray,
        ]);
    }

    /**
     * @param list<string> $methods Pre-rendered method strings.
     */
    private function renderClass(
        string $classNamespace,
        array $methods,
        string $stubName = 'model',
    ): string {
        $stub = $this->loadStub($stubName);

        return $this->compiler->render($stub, [
            'namespace' => Strings::classNamespace($classNamespace),
            'className' => Strings::classBaseName($classNamespace),
            'methods' => implode("\n\n", $methods),
        ]);
    }

    /**
     * @param list<string> $bindings
     * @param array<string, string> $paramAnnotations
     */
    private function buildParamSignature(array $bindings, array $paramAnnotations): string
    {
        $params = [];

        foreach ($bindings as $binding) {
            $type = $paramAnnotations[$binding] ?? 'string';
            $phpName = Strings::camel($binding);
            $params[] = sprintf('%s $%s', $type, $phpName);
        }

        return implode(', ', $params);
    }

    /**
     * @param list<string> $bindings
     */
    private function buildParamsArray(array $bindings): string
    {
        if ($bindings === []) {
            return '[]';
        }

        $entries = [];
        foreach ($bindings as $binding) {
            $phpName = Strings::camel($binding);
            $entries[] = sprintf("'%s' => \$%s", $binding, $phpName);
        }

        return '[' . implode(', ', $entries) . ']';
    }

    private function loadStub(string $name): string
    {
        if ($this->rootDirectory !== '') {
            $appStub = $this->rootDirectory . DIRECTORY_SEPARATOR
                . 'stubs' . DIRECTORY_SEPARATOR . $name . '.stub';

            if ($this->fileSystem->isFile($appStub)) {
                return $this->reader->read($appStub);
            }
        }

        return $this->reader->read(self::STUBS_DIR . '/' . $name . '.stub');
    }
}
