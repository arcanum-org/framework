<?php

declare(strict_types=1);

namespace Arcanum\Shodo;

use Arcanum\Flow\River\LazyResource;
use Arcanum\Flow\River\Stream;
use Arcanum\Hyper\Headers;
use Arcanum\Hyper\Message;
use Arcanum\Hyper\Response;
use Arcanum\Hyper\StatusCode;
use Arcanum\Hyper\Version;
use Arcanum\Parchment\Reader;
use Psr\Http\Message\ResponseInterface;

/**
 * Renders data as a plain text HTTP response using co-located templates.
 *
 * Template discovery follows PSR-4 convention: the DTO class name maps
 * to a .txt file in the same directory as the class. When no template
 * exists, falls back to a structured plain text representation.
 *
 * Uses an identity escape function — no escaping is needed for plain text.
 */
class PlainTextRenderer implements Renderer
{
    public function __construct(
        private readonly TemplateResolver $resolver,
        private readonly TemplateCompiler $compiler,
        private readonly TemplateCache $cache,
        private readonly PlainTextFallback $fallback,
        private readonly Reader $reader = new Reader(),
    ) {
    }

    /**
     * Render data as a plain text response.
     *
     * @param string $dtoClass The DTO class name, used to discover the template.
     */
    public function render(mixed $data, string $dtoClass = ''): ResponseInterface
    {
        $templatePath = $this->resolver->resolve($dtoClass);

        if ($templatePath === null) {
            $text = $this->fallback->render($data);
        } else {
            $text = $this->renderTemplate($templatePath, $data);
        }

        return $this->buildResponse($text);
    }

    private function renderTemplate(string $templatePath, mixed $data): string
    {
        if ($this->cache->isFresh($templatePath)) {
            $compiled = $this->cache->load($templatePath);
        } else {
            $source = $this->reader->read($templatePath);
            $compiled = $this->compiler->compile($source);
            $this->cache->store($templatePath, $compiled);
        }

        $variables = $this->extractVariables($data);
        $variables['__escape'] = static fn(string $value): string => $value;

        return $this->execute($compiled, $variables);
    }

    /**
     * @return array<string, mixed>
     */
    private function extractVariables(mixed $data): array
    {
        if (is_array($data)) {
            /** @var array<string, mixed> */
            return $data;
        }

        if (is_object($data)) {
            return get_object_vars($data);
        }

        return ['data' => $data];
    }

    /**
     * @param array<string, mixed> $variables
     */
    private function execute(string $compiledPhp, array $variables): string
    {
        $executor = static function (string $__compiled, array $__vars): string {
            extract($__vars);
            ob_start();
            eval('?>' . $__compiled);
            return (string) ob_get_clean();
        };

        return $executor($compiledPhp, $variables);
    }

    private function buildResponse(string $text): ResponseInterface
    {
        $body = new Stream(LazyResource::for('php://memory', 'w+'));
        $body->write($text);

        return new Response(
            new Message(
                new Headers([
                    'Content-Type' => ['text/plain; charset=UTF-8'],
                    'Content-Length' => [(string) strlen($text)],
                ]),
                $body,
                Version::v11,
            ),
            StatusCode::OK,
        );
    }
}
