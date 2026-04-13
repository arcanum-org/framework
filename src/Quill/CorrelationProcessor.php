<?php

declare(strict_types=1);

namespace Arcanum\Quill;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

/**
 * Tags every log record with a correlation ID for grouping related log lines.
 *
 * The caller sets the ID at the start of a unit of work (HTTP request, CLI
 * command, queued job) and clears it afterward. When no ID is set, the
 * processor is a no-op.
 */
final class CorrelationProcessor implements ProcessorInterface
{
    private ?string $correlationId = null;

    public function setCorrelationId(string $correlationId): void
    {
        $this->correlationId = $correlationId;
    }

    public function clearCorrelationId(): void
    {
        $this->correlationId = null;
    }

    public function __invoke(LogRecord $record): LogRecord
    {
        if ($this->correlationId === null) {
            return $record;
        }

        return $record->with(extra: array_merge($record->extra, [
            'correlation_id' => $this->correlationId,
        ]));
    }
}
