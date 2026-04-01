<?php

declare(strict_types=1);

namespace Arcanum\Hyper;

use Arcanum\Shodo\CsvFormatter;
use Arcanum\Shodo\Renderer;
use Psr\Http\Message\ResponseInterface;

/**
 * HTTP response adapter for CSV output.
 *
 * Composes a CsvFormatter for data → string conversion, then wraps
 * the result in a ResponseInterface with text/csv content type.
 */
class CsvResponseRenderer implements Renderer
{
    use ResponseBuilder;

    public function __construct(
        private readonly CsvFormatter $formatter = new CsvFormatter(),
    ) {
    }

    public function render(mixed $data, string $dtoClass = ''): ResponseInterface
    {
        $csv = $this->formatter->format($data, $dtoClass);
        return $this->buildResponse($csv, 'text/csv; charset=UTF-8');
    }
}
