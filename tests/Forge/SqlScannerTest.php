<?php

declare(strict_types=1);

namespace Arcanum\Test\Forge;

use Arcanum\Forge\SqlScanner;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(SqlScanner::class)]
final class SqlScannerTest extends TestCase
{
    /**
     * Collect all code characters from a SQL string.
     */
    private function collectCodeChars(string $sql): string
    {
        $chars = '';
        SqlScanner::scan($sql, static function (string $sql, int $pos) use (&$chars): ?int {
            $chars .= $sql[$pos];
            return null;
        });
        return $chars;
    }

    public function testPassesThroughPlainSql(): void
    {
        // Arrange
        $sql = 'SELECT * FROM users';

        // Act
        $code = $this->collectCodeChars($sql);

        // Assert
        $this->assertSame($sql, $code);
    }

    public function testSkipsLineComments(): void
    {
        // Arrange
        $sql = "SELECT * -- this is a comment\nFROM users";

        // Act
        $code = $this->collectCodeChars($sql);

        // Assert
        $this->assertSame('SELECT * FROM users', $code);
    }

    public function testSkipsBlockComments(): void
    {
        // Arrange
        $sql = 'SELECT /* columns */ * FROM users';

        // Act
        $code = $this->collectCodeChars($sql);

        // Assert
        $this->assertSame('SELECT  * FROM users', $code);
    }

    public function testSkipsSingleQuotedStrings(): void
    {
        // Arrange
        $sql = "SELECT * FROM users WHERE name = 'Alice'";

        // Act
        $code = $this->collectCodeChars($sql);

        // Assert
        $this->assertStringNotContainsString('Alice', $code);
        $this->assertStringContainsString('SELECT * FROM users WHERE name = ', $code);
    }

    public function testHandlesEscapedQuotesInStrings(): void
    {
        // Arrange — O'Brien has an escaped quote
        $sql = "SELECT * FROM users WHERE name = 'O''Brien'";

        // Act
        $code = $this->collectCodeChars($sql);

        // Assert — string content (including escaped quote) is skipped
        $this->assertStringNotContainsString('Brien', $code);
    }

    public function testBindingInsideCommentNotVisible(): void
    {
        // Arrange — :name is inside a comment, :id is in code
        $sql = "SELECT * FROM users WHERE id = :id -- AND name = :name";

        // Act — collect colon positions
        $colons = [];
        SqlScanner::scan($sql, static function (string $sql, int $pos) use (&$colons): ?int {
            if ($sql[$pos] === ':') {
                $colons[] = $pos;
            }
            return null;
        });

        // Assert — only :id's colon is visible (the one before "id")
        $this->assertCount(1, $colons);
    }

    public function testBindingInsideStringNotVisible(): void
    {
        // Arrange — :name is inside a string literal
        $sql = "SELECT * FROM users WHERE bio = ':name is great'";

        // Act
        $colons = [];
        SqlScanner::scan($sql, static function (string $sql, int $pos) use (&$colons): ?int {
            if ($sql[$pos] === ':') {
                $colons[] = $pos;
            }
            return null;
        });

        // Assert — no colons visible (it's inside the string)
        $this->assertCount(0, $colons);
    }

    public function testVisitorCanAdvancePosition(): void
    {
        // Arrange — visitor skips 3 characters when it sees 'X'
        $sql = 'ABXCDE';
        $chars = '';

        // Act
        SqlScanner::scan($sql, static function (string $sql, int $pos) use (&$chars): ?int {
            if ($sql[$pos] === 'X') {
                return $pos + 3; // skip X, C, D
            }
            $chars .= $sql[$pos];
            return null;
        });

        // Assert
        $this->assertSame('ABE', $chars);
    }

    public function testHandlesEmptySql(): void
    {
        // Arrange & Act
        $called = false;
        SqlScanner::scan('', static function () use (&$called): ?int {
            $called = true;
            return null;
        });

        // Assert
        $this->assertFalse($called);
    }

    public function testHandlesUnterminatedBlockComment(): void
    {
        // Arrange — block comment never closed
        $sql = 'SELECT /* never closed';

        // Act
        $code = $this->collectCodeChars($sql);

        // Assert — only 'SELECT ' before the comment
        $this->assertSame('SELECT ', $code);
    }
}
