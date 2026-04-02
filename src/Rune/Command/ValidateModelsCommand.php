<?php

declare(strict_types=1);

namespace Arcanum\Rune\Command;

use Arcanum\Forge\Sql;
use Arcanum\Parchment\Reader;
use Arcanum\Rune\Attribute\Description;
use Arcanum\Rune\BuiltInCommand;
use Arcanum\Rune\ExitCode;
use Arcanum\Rune\Input;
use Arcanum\Rune\Output;
use Arcanum\Toolkit\Strings;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;

/**
 * Validates generated Model classes against their SQL files.
 *
 * Reports: missing methods (new SQL files), stale methods (deleted SQL),
 * parameter mismatches, and type mismatches. CI-friendly — returns a
 * non-zero exit code if any drift is detected.
 *
 * Usage: php arcanum validate:models
 */
#[Description('Validate generated Model classes against SQL files')]
final class ValidateModelsCommand implements BuiltInCommand
{
    public function __construct(
        private readonly string $domainRoot,
        private readonly string $domainNamespace,
        private readonly Reader $reader = new Reader(),
    ) {
    }

    public function execute(Input $input, Output $output): int
    {
        if (!is_dir($this->domainRoot)) {
            $output->errorLine(sprintf('Domain root not found: %s', $this->domainRoot));
            return ExitCode::Failure->value;
        }

        $domains = $this->discoverDomains();

        if ($domains === []) {
            $output->writeLine('No Model directories found.');
            return ExitCode::Success->value;
        }

        $issues = [];

        foreach ($domains as $domain => $modelDir) {
            $className = $this->domainNamespace . '\\' . $domain . '\\Model';
            $domainIssues = $this->validateDomain($domain, $modelDir, $className);
            $issues = array_merge($issues, $domainIssues);
        }

        if ($issues === []) {
            $output->writeLine(sprintf(
                'All %d domain model(s) are in sync.',
                count($domains),
            ));
            return ExitCode::Success->value;
        }

        $output->errorLine(sprintf('Found %d issue(s):', count($issues)));
        foreach ($issues as $issue) {
            $output->errorLine('  ' . $issue);
        }

        return ExitCode::Failure->value;
    }

    /**
     * @return list<string>
     */
    private function validateDomain(
        string $domain,
        string $modelDir,
        string $className,
    ): array {
        $issues = [];

        $sqlMethods = $this->discoverSqlMethods($modelDir);

        if (!class_exists($className)) {
            $issues[] = sprintf(
                '%s: no generated class — run forge:models',
                $domain,
            );
            return $issues;
        }

        $reflection = new ReflectionClass($className);
        $classMethods = $this->getPublicMethods($reflection);

        // Missing methods (SQL exists, no generated method).
        foreach ($sqlMethods as $method => $sqlFile) {
            if (!isset($classMethods[$method])) {
                $issues[] = sprintf(
                    '%s: missing method %s() for %s',
                    $domain,
                    $method,
                    basename($sqlFile),
                );
                continue;
            }

            // Parameter validation.
            $paramIssues = $this->validateParams(
                $domain,
                $method,
                $sqlFile,
                $classMethods[$method],
            );
            $issues = array_merge($issues, $paramIssues);
        }

        // Stale methods (generated method exists, SQL deleted).
        foreach ($classMethods as $method => $reflectionMethod) {
            if (!isset($sqlMethods[$method])) {
                $issues[] = sprintf(
                    '%s: stale method %s() — SQL file deleted',
                    $domain,
                    $method,
                );
            }
        }

        return $issues;
    }

    /**
     * @return list<string>
     */
    private function validateParams(
        string $domain,
        string $method,
        string $sqlFile,
        ReflectionMethod $reflectionMethod,
    ): array {
        $issues = [];
        $sql = $this->reader->read($sqlFile);

        $bindings = Sql::extractBindings($sql);
        $paramAnnotations = Sql::parseParams($sql);

        $reflectionParams = $reflectionMethod->getParameters();

        // Check parameter count.
        if (count($reflectionParams) !== count($bindings)) {
            $issues[] = sprintf(
                '%s::%s() has %d param(s) but SQL has %d binding(s)',
                $domain,
                $method,
                count($reflectionParams),
                count($bindings),
            );
            return $issues;
        }

        // Check each parameter name and type.
        foreach ($bindings as $i => $binding) {
            $expectedName = Strings::camel($binding);
            $expectedType = $paramAnnotations[$binding] ?? 'string';

            if (!isset($reflectionParams[$i])) {
                break;
            }

            $param = $reflectionParams[$i];

            if ($param->getName() !== $expectedName) {
                $issues[] = sprintf(
                    '%s::%s() param #%d is $%s, expected $%s',
                    $domain,
                    $method,
                    $i + 1,
                    $param->getName(),
                    $expectedName,
                );
            }

            $type = $param->getType();
            $typeName = $type instanceof ReflectionNamedType ? $type->getName() : '';

            if ($typeName !== $expectedType) {
                $issues[] = sprintf(
                    '%s::%s() param $%s is %s, expected %s',
                    $domain,
                    $method,
                    $param->getName(),
                    $typeName ?: 'untyped',
                    $expectedType,
                );
            }
        }

        return $issues;
    }

    /**
     * @return array<string, string> Method name → SQL file path.
     */
    private function discoverSqlMethods(string $modelDir): array
    {
        $methods = [];
        $files = glob($modelDir . DIRECTORY_SEPARATOR . '*.sql') ?: [];

        foreach ($files as $file) {
            $method = lcfirst(basename($file, '.sql'));
            $methods[$method] = $file;
        }

        return $methods;
    }

    /**
     * @param ReflectionClass<object> $class
     * @return array<string, ReflectionMethod>
     */
    private function getPublicMethods(ReflectionClass $class): array
    {
        $methods = [];

        foreach ($class->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            // Skip inherited methods from Model base class.
            if ($method->getDeclaringClass()->getName() !== $class->getName()) {
                continue;
            }

            $methods[$method->getName()] = $method;
        }

        return $methods;
    }

    /**
     * @return array<string, string> Domain name → Model directory path.
     */
    private function discoverDomains(): array
    {
        $domains = [];
        $this->scanDirectory($this->domainRoot, '', $domains);
        ksort($domains);

        return $domains;
    }

    /**
     * @param array<string, string> $domains
     */
    private function scanDirectory(
        string $dir,
        string $prefix,
        array &$domains,
    ): void {
        $entries = scandir($dir);
        if ($entries === false) {
            return;
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $dir . DIRECTORY_SEPARATOR . $entry;

            if (!is_dir($path)) {
                continue;
            }

            if ($entry === 'Model') {
                $sqlFiles = glob($path . DIRECTORY_SEPARATOR . '*.sql');
                if ($sqlFiles !== false && $sqlFiles !== []) {
                    $domain = ltrim($prefix, '\\');
                    if ($domain !== '') {
                        $domains[$domain] = $path;
                    }
                }
                continue;
            }

            if (in_array($entry, ['Command', 'Query'], true)) {
                continue;
            }

            $this->scanDirectory(
                $path,
                $prefix !== '' ? $prefix . '\\' . $entry : $entry,
                $domains,
            );
        }
    }
}
