<?php

declare(strict_types=1);

namespace Arcanum\Quill;

use Psr\Log\LoggerInterface;

interface ChannelLogger
{
    public function channel(string $name): Channel;
}
