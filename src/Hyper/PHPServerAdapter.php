<?php

declare(strict_types=1);

namespace Arcanum\Hyper;

/**
 * PHP Server Adapter
 * ------------------
 *
 * This class is a wrapper around PHP's built-in functions for interacting with
 * headers, output buffers, and the request/response cycle.
 *
 * Since these are just simple function wrappers, we don't need to test them.
 * @codeCoverageIgnore
 */
class PHPServerAdapter implements ServerAdapter
{
    /**
     * @return array<string,string> The HTTP header key/value pairs.
     */
    public function getallheaders(): array
    {
        return \getallheaders();
    }

    /**
     * Checks if or where headers have been sent.
     *
     * You can't add any more header lines using the header() function once the
     * header block has already been sent. Using this function you can at least
     * prevent getting HTTP header related error messages.
     */
    public function headersSent(string|null &$file = null, int|null &$line = null): bool
    {
        return \headers_sent($file, $line);
    }

    /**
     * Send a raw HTTP header.
     *
     * Remember that header() must be called before any actual output is sent
     */
    public function header(string $header, bool $replace = true, int $responseCode = 0): void
    {
        \header($header, $replace, $responseCode);
    }

    /**
     * Echo content to the client.
     */
    public function echo(string $content): void
    {
        echo $content;
    }

    /**
     * Should call fastcgi_finish_request() if possible, and should return
     * false if fastcgi_finish_request() is not available.
     */
    public function fastCGIFinishRequest(): bool
    {
        if (\function_exists('fastcgi_finish_request')) {
            \fastcgi_finish_request();
            return true;
        }
        return false;
    }

    /**
     * Should call litespeed_finish_request() if possible, and should return
     * false if litespeed_finish_request() is not available.
     */
    public function litespeedFinishRequest(): bool
    {
        $lightspeedFinishRequest = 'litespeed_finish_request';
        if (\function_exists($lightspeedFinishRequest)) {
            $lightspeedFinishRequest();
            return true;
        }
        return false;
    }

    /**
     * Flush the system write buffers.
     */
    public function flush(): void
    {
        \flush();
    }

    /**
     * Get status information on either the top level output buffer or all
     * active output buffer levels if $fullStatus is true.
     *
     * @return array{
     *   level: int,
     *   type?: 0|1,
     *   status?: int,
     *   name?: string,
     *   del?: int
     * }|(array<int, array{
     *   type?: 0|1,
     *   status?: int,
     *   name?: string,
     *   del?: int,
     *   chunk_size?: int,
     *   size?: int,
     *   block_size?: int,
     * }>)
     */
    public function obGetStatus(bool $fullStatus = false): array
    {
        return \ob_get_status($fullStatus);
    }

    /**
     * Call ob_end_flush()
     */
    public function obEndFlush(): bool
    {
        return \ob_end_flush();
    }

    /**
     * Call ob_end_clean()
     */
    public function obEndClean(): bool
    {
        return \ob_end_clean();
    }
}
