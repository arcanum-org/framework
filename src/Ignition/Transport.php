<?php

declare(strict_types=1);

namespace Arcanum\Ignition;

/**
 * Identifies the active transport layer.
 *
 * Each kernel registers its Transport value in the container during
 * bootstrap so that transport-aware middleware (e.g., TransportGuard)
 * can check which transport is active.
 */
enum Transport: string
{
    case Http = 'http';
    case Cli = 'cli';
}
