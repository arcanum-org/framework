<?php

declare(strict_types=1);

/**
 * Built-in fallback error page.
 *
 * This is a pre-compiled PHP template executed via extract() + require,
 * deliberately bypassing the Shodo TemplateEngine. The error page must
 * render even when the template engine, cache, or configuration is
 * broken — it's the last resort before a blank screen.
 *
 * Variables (all pre-escaped by the caller):
 *   $code        - HTTP status code (int)
 *   $title       - status title (string)
 *   $message     - error description (string)
 *   $suggestion  - ArcanumException hint or null (string|null)
 *   $exception   - exception class name or null (string|null, debug only)
 *   $file        - source file or null (string|null, debug only)
 *   $line        - source line or null (int|null, debug only)
 *   $trace       - stack trace or null (string|null, debug only)
 *
 * @var int $code
 * @var string $title
 * @var string $message
 * @var string|null $suggestion
 * @var string|null $exception
 * @var string|null $file
 * @var int|null $line
 * @var string|null $trace
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $code ?> <?= $title ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <?php // phpcs:ignore Generic.Files.LineLength.TooLong ?>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500&family=JetBrains+Mono:wght@400&family=Lora:wght@600&display=swap" rel="stylesheet">
    <style>
        body {
            margin:0; padding:0; min-height:100vh;
            display:flex; align-items:center; justify-content:center;
            background:#faf8f1;
            font-family:Inter,system-ui,-apple-system,'Segoe UI',sans-serif;
            color:#2c2a25;
        }
        .container { max-width:480px; width:100%; padding:24px; }
        .status-code {
            margin:0 0 8px; font-family:Lora,Georgia,'Times New Roman',serif;
            font-size:48px; font-weight:600;
            line-height:1.10; letter-spacing:-0.5px; color:#b5623f;
        }
        h1 {
            margin:0 0 8px; font-family:Lora,Georgia,'Times New Roman',serif;
            font-size:28px; font-weight:600; line-height:1.20; color:#2c2a25;
        }
        .message { margin:0; font-size:16px; line-height:1.65; color:#6b675e; }
        .suggestion {
            margin:16px 0 0; padding:14px 18px;
            border-left:3px solid #4a6fa5; background:rgba(74,111,165,0.08);
            border-radius:6px; color:#4a6fa5; font-size:14px; line-height:1.55;
        }
        .debug {
            margin-top:32px; padding-top:24px; border-top:1px solid #ddd9ce;
        }
        .debug-class {
            margin:0 0 8px; font-family:'JetBrains Mono','Fira Code','Source Code Pro',Consolas,monospace;
            font-size:14px; color:#3d3a34;
        }
        .debug-file {
            margin:0 0 16px; font-family:'JetBrains Mono','Fira Code','Source Code Pro',Consolas,monospace;
            font-size:13px; color:#6b675e;
        }
        details summary {
            cursor:pointer; font-family:Inter,system-ui,-apple-system,'Segoe UI',sans-serif;
            font-size:14px; font-weight:500; color:#6b675e; margin-bottom:8px;
        }
        details pre {
            margin:0; padding:16px 20px; background:#eae6da;
            border:1px solid #ddd9ce; border-radius:6px; overflow-x:auto;
            font-family:'JetBrains Mono','Fira Code','Source Code Pro',Consolas,monospace;
            font-size:13px; line-height:1.55; color:#3d3a34;
        }
        .actions { margin-top:32px; display:flex; gap:12px; }
        .actions a {
            display:inline-block; padding:10px 20px; border-radius:6px;
            border:1px solid #c4bfb3; background:transparent; color:#b5623f;
            font-family:Inter,system-ui,-apple-system,'Segoe UI',sans-serif;
            font-size:15px; font-weight:500; text-decoration:none;
        }
        .actions a:hover { background:#ece9e0; }
        @media (prefers-color-scheme: dark) {
            body { background:#1a1915; color:#e8e4db; }
            .status-code { color:#c8795a; }
            h1 { color:#e8e4db; }
            .message { color:#9c9789; }
            .suggestion {
                border-color:#6a8fc0; color:#6a8fc0;
                background:rgba(106,143,192,0.1);
            }
            .debug { border-color:#3d3a34; }
            .debug-class { color:#c4bfb3; }
            .debug-file { color:#9c9789; }
            details summary { color:#9c9789; }
            details pre {
                background:#2d2b24; border-color:#3d3a34; color:#c4bfb3;
            }
            .actions a {
                border-color:#3d3a34; color:#c8795a;
            }
            .actions a:hover { background:#2d2b24; }
        }
    </style>
</head>
<body>
    <div class="container">
        <p class="status-code"><?= $code ?></p>
        <h1><?= $title ?></h1>
        <p class="message"><?= $message ?></p>
        <?php if ($suggestion !== null) : ?>
        <p class="suggestion"><?= $suggestion ?></p>
        <?php endif; ?>
        <?php if ($trace !== null) : ?>
        <div class="debug">
            <p class="debug-class"><?= $exception ?></p>
            <p class="debug-file"><?= $file ?>:<?= $line ?></p>
            <details>
                <summary>Stack trace</summary>
                <pre><?= $trace ?></pre>
            </details>
        </div>
        <?php endif; ?>
        <div class="actions">
            <a href="javascript:history.back()">Go back</a>
            <a href="/">Go home</a>
        </div>
    </div>
</body>
</html>
