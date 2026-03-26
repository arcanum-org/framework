<?php

declare(strict_types=1);

namespace Arcanum\Glitch;

class JsonReporter implements Reporter
{
    public function __construct(
        private bool $debug = false,
    ) {
    }

    /**
     * Report an exception as JSON.
     */
    public function __invoke(\Throwable $e): void
    {
        $data = [
            'error' => [
                'code' => $this->getStatusCode($e),
                'message' => $e->getMessage(),
            ],
        ];

        if ($this->debug) {
            $data['error']['exception'] = get_class($e);
            $data['error']['file'] = $e->getFile();
            $data['error']['line'] = $e->getLine();
            $data['error']['trace'] = $e->getTrace();
        }

        echo json_encode($data, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES);
    }

    /**
     * Check if this reporter handles the given exception.
     *
     * @param class-string<\Throwable> $exceptionName
     */
    public function handles(string $exceptionName): bool
    {
        return true;
    }

    /**
     * Get the HTTP status code for the given exception.
     */
    private function getStatusCode(\Throwable $e): int
    {
        $code = $e->getCode();

        if (is_int($code) && $code >= 400 && $code <= 599) {
            return $code;
        }

        return 500;
    }
}
