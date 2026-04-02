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

    // ── parseCasts ───────────────────────────────────────────────

    public function testParseCastsExtractsAnnotations(): void
    {
        $sql = "-- @cast price float\n-- @cast active bool\nSELECT price, active FROM products";

        $this->assertSame(['price' => 'float', 'active' => 'bool'], Sql::parseCasts($sql));
    }

    public function testParseCastsReturnsEmptyWhenNone(): void
    {
        $this->assertSame([], Sql::parseCasts('SELECT * FROM users'));
    }

    public function testParseCastsIgnoresRegularComments(): void
    {
        $sql = "-- This is a regular comment\n-- @cast id int\nSELECT id FROM users";

        $this->assertSame(['id' => 'int'], Sql::parseCasts($sql));
    }

    public function testParseCastsAllTypes(): void
    {
        $sql = "-- @cast a int\n-- @cast b float\n-- @cast c bool\n-- @cast d json\nSELECT a, b, c, d FROM t";

        $this->assertSame(
            ['a' => 'int', 'b' => 'float', 'c' => 'bool', 'd' => 'json'],
            Sql::parseCasts($sql),
        );
    }

    // ── applyCasts ───────────────────────────────────────────────

    public function testApplyCastsInt(): void
    {
        $rows = [['id' => '42', 'name' => 'Alice']];

        $result = Sql::applyCasts($rows, ['id' => 'int']);

        $this->assertSame(42, $result[0]['id']);
        $this->assertSame('Alice', $result[0]['name']);
    }

    public function testApplyCastsFloat(): void
    {
        $rows = [['price' => '19.99']];

        $this->assertSame(19.99, Sql::applyCasts($rows, ['price' => 'float'])[0]['price']);
    }

    public function testApplyCastsBoolVariants(): void
    {
        $rows = [
            ['active' => '1'],
            ['active' => '0'],
            ['active' => 't'],
            ['active' => 'f'],
            ['active' => 'true'],
            ['active' => 'yes'],
            ['active' => 'false'],
            ['active' => 'no'],
        ];

        $result = Sql::applyCasts($rows, ['active' => 'bool']);

        $this->assertTrue($result[0]['active']);   // '1'
        $this->assertFalse($result[1]['active']);  // '0'
        $this->assertTrue($result[2]['active']);   // 't'
        $this->assertFalse($result[3]['active']);  // 'f'
        $this->assertTrue($result[4]['active']);   // 'true'
        $this->assertTrue($result[5]['active']);   // 'yes'
        $this->assertFalse($result[6]['active']);  // 'false'
        $this->assertFalse($result[7]['active']);  // 'no'
    }

    public function testApplyCastsJson(): void
    {
        $rows = [['data' => '{"key":"value"}']];

        $this->assertSame(['key' => 'value'], Sql::applyCasts($rows, ['data' => 'json'])[0]['data']);
    }

    public function testApplyCastsPreservesNull(): void
    {
        $rows = [['value' => null]];

        $this->assertNull(Sql::applyCasts($rows, ['value' => 'int'])[0]['value']);
    }

    public function testApplyCastsSkipsMissingColumns(): void
    {
        $rows = [['name' => 'Alice']];

        $result = Sql::applyCasts($rows, ['missing' => 'int']);

        $this->assertSame('Alice', $result[0]['name']);
    }

    public function testApplyCastsEmptyCastsReturnsUnchanged(): void
    {
        $rows = [['id' => '1']];

        $this->assertSame($rows, Sql::applyCasts($rows, []));
    }

    public function testApplyCastsUnknownTypePassesThrough(): void
    {
        $rows = [['value' => 'hello']];

        $this->assertSame('hello', Sql::applyCasts($rows, ['value' => 'unknown'])[0]['value']);
    }
}
