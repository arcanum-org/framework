<?php

declare(strict_types=1);

namespace Arcanum\Hyper\URI;

final class Port implements \Stringable
{
    /**
     * Default ports for schemes.
     */
    public const DEFAULT_PORTS = [
        'http' => 80,
        'https' => 443,
        'ftp' => 21,
        'gopher' => 70,
        'nntp' => 119,
        'news' => 119,
        'telnet' => 23,
        'tn3270' => 23,
        'imap' => 143,
        'pop' => 110,
        'ldap' => 389,
    ];

    /**
     * Port.
     */
    public function __construct(private int|string $value)
    {
        if (is_string($value)) {
            $this->value = (int) $value;
        }
        if ($this->value < 0 || $this->value > 65535) {
            throw new \InvalidArgumentException('Port must be between 0 and 65535.');
        }
    }

    /**
     * Port as a string.
     */
    public function __toString(): string
    {
        return (string) $this->value;
    }
}
