<?php

declare(strict_types=1);

namespace Arcanum\Forge\Migration;

/**
 * Parses a migration .sql file into a MigrationFile value object.
 *
 * Stateless, no dependencies. The filesystem read happens outside
 * this class — it receives a path (for the filename) and the raw
 * file contents as a string.
 *
 * Expected format:
 *
 *   -- @migrate up
 *   CREATE TABLE users (...);
 *
 *   -- @migrate down
 *   DROP TABLE users;
 *
 * The transaction opt-out pragma `-- @transaction off` may appear
 * on a line immediately after the `-- @migrate up` or `-- @migrate down`
 * marker. When absent, the migration runs inside a transaction.
 */
final class MigrationParser
{
    private const FILENAME_PATTERN = '/^(\d{14})_(.+)\.sql$/';

    private const UP_MARKER = '-- @migrate up';

    private const DOWN_MARKER = '-- @migrate down';

    private const TRANSACTION_OFF = '-- @transaction off';

    /**
     * Parse a migration file.
     *
     * @param string $path     Full filesystem path (used for filename extraction).
     * @param string $contents Raw file contents.
     *
     * @throws InvalidMigrationFile On malformed filename or missing markers.
     */
    public function parse(string $path, string $contents): MigrationFile
    {
        $filename = basename($path);

        if (!preg_match(self::FILENAME_PATTERN, $filename, $matches)) {
            throw new InvalidMigrationFile(sprintf(
                'Migration filename "%s" does not match the expected format '
                    . '{YmdHis}_{name}.sql (e.g. 20260409120000_create_users.sql).',
                $filename,
            ));
        }

        $version = $matches[1];
        $name = $matches[2];

        $upPos = strpos($contents, self::UP_MARKER);
        if ($upPos === false) {
            throw new InvalidMigrationFile(sprintf(
                'Migration "%s" is missing the "-- @migrate up" marker.',
                $filename,
            ));
        }

        $downPos = strpos($contents, self::DOWN_MARKER);
        if ($downPos === false) {
            throw new InvalidMigrationFile(sprintf(
                'Migration "%s" is missing the "-- @migrate down" marker.',
                $filename,
            ));
        }

        // Extract the up section: everything between the up marker line and the down marker.
        $afterUp = $upPos + strlen(self::UP_MARKER);
        $upBody = substr($contents, $afterUp, $downPos - $afterUp);

        // Extract the down section: everything after the down marker line.
        $afterDown = $downPos + strlen(self::DOWN_MARKER);
        $downBody = substr($contents, $afterDown);

        // Check for -- @transaction off in each section.
        $transactional = true;
        if (str_contains($upBody, self::TRANSACTION_OFF)) {
            $transactional = false;
            $upBody = str_replace(self::TRANSACTION_OFF, '', $upBody);
        }
        if (str_contains($downBody, self::TRANSACTION_OFF)) {
            $downBody = str_replace(self::TRANSACTION_OFF, '', $downBody);
        }

        return new MigrationFile(
            version: $version,
            name: $name,
            filename: $filename,
            upSql: trim($upBody),
            downSql: trim($downBody),
            transactional: $transactional,
            checksum: md5($contents),
        );
    }
}
