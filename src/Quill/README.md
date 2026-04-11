# Arcanum Quill

Quill is the logging package. It wraps Monolog behind a PSR-3 interface with multi-channel support — route different log messages to different destinations by channel name.

## How it works

`Logger` is the entry point. It implements both PSR-3 `LoggerInterface` and `ChannelLogger`, so it works as a standard logger and as a channel router:

```php
// Standard PSR-3 logging — goes to the 'default' channel
$logger->info('User logged in', ['user_id' => 42]);
$logger->error('Payment failed', ['order' => $orderId]);

// Channel-specific logging
$logger->channel('payments')->critical('Gateway timeout', ['provider' => 'stripe']);
$logger->channel('deprecations')->warning('Method X is deprecated');
```

## Channels

A `Channel` is a PSR-3 logger wrapping a single Monolog instance. Each channel can have its own handlers (file, syslog, error_log, etc.) and formatting:

```php
use Arcanum\Quill\Channel;
use Arcanum\Quill\Logger;

$default = new Channel(new \Monolog\Logger('default', [$streamHandler]));
$payments = new Channel(new \Monolog\Logger('payments', [$rotatingHandler]));

$logger = new Logger($default, $payments);
```

If no `default` channel is provided, one is created automatically with no handlers.

### Adding channels at runtime

```php
$logger->addChannel(new Channel(
    new \Monolog\Logger('audit', [$syslogHandler])
));

$logger->channel('audit')->info('Admin action', ['action' => 'ban_user']);
```

Requesting a channel that doesn't exist throws `InvalidArgumentException`.

## Configuration

In practice, channels and handlers are configured in `config/log.php` and wired by the `Bootstrap\Logger` bootstrapper:

```php
// config/log.php
return [
    'handlers' => [
        'daily' => [
            'type' => 'rotating_file',
            'path' => 'files/logs/app.log',
            'level' => 'debug',
            'maxFiles' => 30,
        ],
        'stderr' => [
            'type' => 'stream',
            'path' => 'php://stderr',
            'level' => 'error',
        ],
        'syslog' => [
            'type' => 'syslog',
            'ident' => 'arcanum',
            'level' => 'warning',
        ],
    ],
    'channels' => [
        'default' => ['daily', 'stderr'],
        'payments' => ['daily'],
        'deprecations' => ['daily'],
    ],
];
```

### Handler types

| Type | Monolog handler | Key options |
|---|---|---|
| `stream` | `StreamHandler` | `path`, `level` |
| `rotating_file` | `RotatingFileHandler` | `path`, `level`, `maxFiles` (default: 30) |
| `syslog` | `SyslogHandler` | `ident`, `level` |
| `error_log` | `ErrorLogHandler` | `level` |
| `process` | `ProcessHandler` | `command`, `level` |

## The interfaces

- **ChannelLogger** — `channel(string $name): Channel` — the multi-channel contract
- **Channel** — PSR-3 `LoggerInterface` wrapping a single Monolog logger
- **Logger** — implements both `LoggerInterface` and `ChannelLogger`

Standard PSR-3 calls (`$logger->info(...)`) always route to the `default` channel. Use `$logger->channel('name')` to target a specific channel.

## At a glance

```
Logger (implements LoggerInterface, ChannelLogger)
|-- emergency/alert/critical/error/warning/notice/info/debug → default channel
|-- channel(name) → specific Channel instance
\-- addChannel() → register channels at runtime

Channel (implements LoggerInterface)
|-- Wraps a single Monolog\Logger
\-- name (readonly) — extracted from the Monolog instance

Handler (enum) — available Monolog handler types
|-- STREAM, ROTATING_FILE, SYSLOG, ERROR_LOG, PROCESS
```
