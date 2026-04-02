<?php

declare(strict_types=1);

namespace Arcanum\Test\Forge;

use Arcanum\Forge\Sql;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(Sql::class)]
final class SqlTest extends TestCase
{
    // ── Basic reads ──────────────────────────────────────────────

    public function testSelectIsRead(): void
    {
        $this->assertTrue(Sql::isRead('SELECT id, name FROM users'));
    }

    public function testSelectLowercaseIsRead(): void
    {
        $this->assertTrue(Sql::isRead('select id from users'));
    }

    public function testSelectMixedCaseIsRead(): void
    {
        $this->assertTrue(Sql::isRead('SeLeCt id from users'));
    }

    public function testSelectWithLeadingWhitespace(): void
    {
        $this->assertTrue(Sql::isRead("  \t\n  SELECT id FROM users"));
    }

    // ── Basic writes ─────────────────────────────────────────────

    public function testInsertIsWrite(): void
    {
        $this->assertFalse(Sql::isRead('INSERT INTO users (name) VALUES (:name)'));
    }

    public function testUpdateIsWrite(): void
    {
        $this->assertFalse(Sql::isRead('UPDATE users SET name = :name WHERE id = :id'));
    }

    public function testDeleteIsWrite(): void
    {
        $this->assertFalse(Sql::isRead('DELETE FROM users WHERE id = :id'));
    }

    public function testCreateTableIsWrite(): void
    {
        $this->assertFalse(Sql::isRead('CREATE TABLE users (id INTEGER PRIMARY KEY)'));
    }

    public function testDropTableIsWrite(): void
    {
        $this->assertFalse(Sql::isRead('DROP TABLE users'));
    }

    public function testAlterTableIsWrite(): void
    {
        $this->assertFalse(Sql::isRead('ALTER TABLE users ADD COLUMN email TEXT'));
    }

    public function testTruncateIsWrite(): void
    {
        $this->assertFalse(Sql::isRead('TRUNCATE TABLE users'));
    }

    // ── Line comments (--) ───────────────────────────────────────

    public function testSelectAfterSingleLineComment(): void
    {
        $this->assertTrue(Sql::isRead("-- fetch users\nSELECT * FROM users"));
    }

    public function testSelectAfterMultipleLineComments(): void
    {
        $this->assertTrue(Sql::isRead("-- first comment\n-- second comment\nSELECT * FROM users"));
    }

    public function testInsertAfterLineComment(): void
    {
        $this->assertFalse(Sql::isRead("-- insert a user\nINSERT INTO users (name) VALUES (:name)"));
    }

    public function testLineCommentOnlyNoNewline(): void
    {
        $this->assertFalse(Sql::isRead('-- just a comment'));
    }

    public function testLineCommentOnlyWithNewline(): void
    {
        $this->assertFalse(Sql::isRead("-- just a comment\n"));
    }

    // ── Block comments (/* */) ───────────────────────────────────

    public function testSelectAfterBlockComment(): void
    {
        $this->assertTrue(Sql::isRead('/* fetch users */ SELECT * FROM users'));
    }

    public function testSelectAfterMultiLineBlockComment(): void
    {
        $sql = <<<'SQL'
        /*
         * Fetch all active users
         * for the dashboard.
         */
        SELECT * FROM users WHERE active = 1
        SQL;

        $this->assertTrue(Sql::isRead($sql));
    }

    public function testInsertAfterBlockComment(): void
    {
        $this->assertFalse(Sql::isRead('/* create user */ INSERT INTO users (name) VALUES (:name)'));
    }

    public function testBlockCommentOnly(): void
    {
        $this->assertFalse(Sql::isRead('/* just a comment */'));
    }

    public function testUnclosedBlockComment(): void
    {
        $this->assertFalse(Sql::isRead('/* unclosed comment SELECT * FROM users'));
    }

    // ── Mixed comment styles ─────────────────────────────────────

    public function testMixedLineAndBlockComments(): void
    {
        $sql = <<<'SQL'
        -- @cast price float
        -- @cast active bool
        /* Products query */
        SELECT id, name, price, active FROM products
        SQL;

        $this->assertTrue(Sql::isRead($sql));
    }

    public function testBlockThenLineCommentBeforeSelect(): void
    {
        $sql = "/* block */\n-- line\nSELECT 1";

        $this->assertTrue(Sql::isRead($sql));
    }

    // ── CTEs (WITH) ──────────────────────────────────────────────

    public function testCteWithSelectIsRead(): void
    {
        $sql = <<<'SQL'
        WITH active_users AS (
            SELECT id, name FROM users WHERE active = 1
        )
        SELECT * FROM active_users
        SQL;

        $this->assertTrue(Sql::isRead($sql));
    }

    public function testCteWithCommentIsRead(): void
    {
        $sql = <<<'SQL'
        -- top customers by revenue
        WITH revenue AS (
            SELECT customer_id, SUM(total) as total
            FROM orders
            GROUP BY customer_id
        )
        SELECT c.name, r.total
        FROM customers c
        JOIN revenue r ON r.customer_id = c.id
        SQL;

        $this->assertTrue(Sql::isRead($sql));
    }

    // ── EXPLAIN ──────────────────────────────────────────────────

    public function testExplainIsRead(): void
    {
        $this->assertTrue(Sql::isRead('EXPLAIN SELECT * FROM users'));
    }

    public function testExplainAnalyzeIsRead(): void
    {
        $this->assertTrue(Sql::isRead('EXPLAIN ANALYZE SELECT * FROM users'));
    }

    // ── SHOW / DESCRIBE / PRAGMA ─────────────────────────────────

    public function testShowIsRead(): void
    {
        $this->assertTrue(Sql::isRead('SHOW TABLES'));
    }

    public function testDescribeIsRead(): void
    {
        $this->assertTrue(Sql::isRead('DESCRIBE users'));
    }

    public function testDescAbbreviationIsRead(): void
    {
        $this->assertTrue(Sql::isRead('DESC users'));
    }

    public function testPragmaIsRead(): void
    {
        $this->assertTrue(Sql::isRead('PRAGMA table_info(users)'));
    }

    // ── Parenthesized subqueries ─────────────────────────────────

    public function testParenthesizedSelectIsRead(): void
    {
        $this->assertTrue(Sql::isRead('(SELECT id FROM users)'));
    }

    public function testNestedParenthesesBeforeSelect(): void
    {
        $this->assertTrue(Sql::isRead('((SELECT id FROM users))'));
    }

    // ── Empty / degenerate input ─────────────────────────────────

    public function testEmptyStringIsWrite(): void
    {
        $this->assertFalse(Sql::isRead(''));
    }

    public function testWhitespaceOnlyIsWrite(): void
    {
        $this->assertFalse(Sql::isRead("   \t\n  "));
    }

    // ── firstKeyword ─────────────────────────────────────────────

    public function testFirstKeywordReturnsSelect(): void
    {
        $this->assertSame('SELECT', Sql::firstKeyword('SELECT * FROM users'));
    }

    public function testFirstKeywordSkipsComments(): void
    {
        $this->assertSame('INSERT', Sql::firstKeyword("-- comment\nINSERT INTO users"));
    }

    public function testFirstKeywordSkipsBlockComment(): void
    {
        $this->assertSame('UPDATE', Sql::firstKeyword('/* block */ UPDATE users SET x = 1'));
    }

    public function testFirstKeywordReturnsEmptyForEmptyInput(): void
    {
        $this->assertSame('', Sql::firstKeyword(''));
    }

    public function testFirstKeywordReturnsEmptyForCommentOnly(): void
    {
        $this->assertSame('', Sql::firstKeyword('-- just a comment'));
    }

    public function testFirstKeywordPreservesOriginalCase(): void
    {
        $this->assertSame('select', Sql::firstKeyword('select * from users'));
    }

    public function testFirstKeywordSkipsParenthesis(): void
    {
        $this->assertSame('SELECT', Sql::firstKeyword('(SELECT 1)'));
    }

    public function testFirstKeywordSkipsCommentsInterleavedWithParentheses(): void
    {
        $this->assertSame('INSERT', Sql::firstKeyword("-- comment\n ( \n --comment\n INSERT INTO users"));
    }
}
