<?php

declare(strict_types=1);

namespace Arcanum\Test\Shodo\Helpers;

use Arcanum\Shodo\Helpers\HtmlHelper;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(HtmlHelper::class)]
final class HtmlHelperTest extends TestCase
{
    // ---------------------------------------------------------------
    // url() — scheme validation
    // ---------------------------------------------------------------

    public function testUrlAllowsHttp(): void
    {
        $helper = new HtmlHelper();

        $this->assertSame('http://example.com', $helper->url('http://example.com'));
    }

    public function testUrlAllowsHttps(): void
    {
        $helper = new HtmlHelper();

        $this->assertSame('https://example.com/page', $helper->url('https://example.com/page'));
    }

    public function testUrlAllowsMailto(): void
    {
        $helper = new HtmlHelper();

        $this->assertSame('mailto:user@example.com', $helper->url('mailto:user@example.com'));
    }

    public function testUrlAllowsTel(): void
    {
        $helper = new HtmlHelper();

        $this->assertSame('tel:+1234567890', $helper->url('tel:+1234567890'));
    }

    public function testUrlRejectsJavascript(): void
    {
        $helper = new HtmlHelper();

        $this->assertSame('', $helper->url('javascript:alert(1)'));
    }

    public function testUrlRejectsJavascriptMixedCase(): void
    {
        $helper = new HtmlHelper();

        $this->assertSame('', $helper->url('JavaScript:alert(1)'));
    }

    public function testUrlRejectsData(): void
    {
        $helper = new HtmlHelper();

        $this->assertSame('', $helper->url('data:text/html,<script>alert(1)</script>'));
    }

    public function testUrlRejectsVbscript(): void
    {
        $helper = new HtmlHelper();

        $this->assertSame('', $helper->url('vbscript:MsgBox("XSS")'));
    }

    public function testUrlRejectsJavascriptWithNullBytes(): void
    {
        $helper = new HtmlHelper();

        $this->assertSame('', $helper->url("java\x00script:alert(1)"));
    }

    public function testUrlRejectsJavascriptWithControlChars(): void
    {
        $helper = new HtmlHelper();

        $this->assertSame('', $helper->url("\x01javascript:alert(1)"));
    }

    public function testUrlAllowsRelativePath(): void
    {
        $helper = new HtmlHelper();

        $this->assertSame('/about', $helper->url('/about'));
    }

    public function testUrlAllowsDotRelativePath(): void
    {
        $helper = new HtmlHelper();

        $this->assertSame('./page', $helper->url('./page'));
    }

    public function testUrlAllowsFragment(): void
    {
        $helper = new HtmlHelper();

        $this->assertSame('#section', $helper->url('#section'));
    }

    public function testUrlAllowsQueryString(): void
    {
        $helper = new HtmlHelper();

        $this->assertSame('?page=2', $helper->url('?page=2'));
    }

    public function testUrlAllowsPathWithoutScheme(): void
    {
        $helper = new HtmlHelper();

        $this->assertSame('page/about', $helper->url('page/about'));
    }

    public function testUrlReturnsEmptyForEmptyString(): void
    {
        $helper = new HtmlHelper();

        $this->assertSame('', $helper->url(''));
    }

    public function testUrlTrimsWhitespace(): void
    {
        $helper = new HtmlHelper();

        $this->assertSame('https://example.com', $helper->url('  https://example.com  '));
    }

    public function testUrlRejectsUnknownScheme(): void
    {
        $helper = new HtmlHelper();

        $this->assertSame('', $helper->url('ftp://files.example.com'));
    }

    // ---------------------------------------------------------------
    // js() — JavaScript string encoding
    // ---------------------------------------------------------------

    public function testJsEncodesQuotes(): void
    {
        $helper = new HtmlHelper();

        $this->assertSame('\\u0027\\u0022', $helper->js('\'"'));
    }

    public function testJsEncodesAngleBrackets(): void
    {
        $helper = new HtmlHelper();

        $this->assertSame('\\u003C\\u002Fscript\\u003E', $helper->js('</script>'));
    }

    public function testJsPreservesAlphanumeric(): void
    {
        $helper = new HtmlHelper();

        $this->assertSame('hello123', $helper->js('hello123'));
    }

    public function testJsEncodesAmpersand(): void
    {
        $helper = new HtmlHelper();

        $this->assertSame('a\\u0026b', $helper->js('a&b'));
    }

    public function testJsReturnsEmptyForEmptyString(): void
    {
        $helper = new HtmlHelper();

        $this->assertSame('', $helper->js(''));
    }

    public function testJsPreservesCommaAndDotAndUnderscore(): void
    {
        $helper = new HtmlHelper();

        $this->assertSame('a,b.c_d', $helper->js('a,b.c_d'));
    }

    // ---------------------------------------------------------------
    // attr() — HTML attribute encoding
    // ---------------------------------------------------------------

    public function testAttrEncodesQuotes(): void
    {
        $helper = new HtmlHelper();

        $this->assertSame('&#x27;&#x22;', $helper->attr('\'"'));
    }

    public function testAttrEncodesAngleBrackets(): void
    {
        $helper = new HtmlHelper();

        $this->assertSame('&#x3C;script&#x3E;', $helper->attr('<script>'));
    }

    public function testAttrPreservesAlphanumeric(): void
    {
        $helper = new HtmlHelper();

        $this->assertSame('hello123', $helper->attr('hello123'));
    }

    public function testAttrReplacesControlCharsWithReplacementChar(): void
    {
        $helper = new HtmlHelper();

        $this->assertSame('&#xFFFD;', $helper->attr("\x00"));
        $this->assertSame('&#xFFFD;', $helper->attr("\x01"));
    }

    public function testAttrPreservesTabAndNewline(): void
    {
        $helper = new HtmlHelper();

        // Tab (0x09) and newline (0x0A) are defined HTML characters.
        $this->assertSame('&#x09;', $helper->attr("\t"));
        $this->assertSame('&#x0A;', $helper->attr("\n"));
    }

    public function testAttrReturnsEmptyForEmptyString(): void
    {
        $helper = new HtmlHelper();

        $this->assertSame('', $helper->attr(''));
    }

    // ---------------------------------------------------------------
    // css() — CSS hex encoding
    // ---------------------------------------------------------------

    public function testCssEncodesSpecialChars(): void
    {
        $helper = new HtmlHelper();

        $this->assertSame('\\3A \\28 \\29 ', $helper->css(':()'));
    }

    public function testCssPreservesAlphanumeric(): void
    {
        $helper = new HtmlHelper();

        $this->assertSame('red', $helper->css('red'));
    }

    public function testCssEncodesExpressionAttempt(): void
    {
        $helper = new HtmlHelper();

        $result = $helper->css('expression(alert(1))');

        $this->assertStringNotContainsString('(', $result);
        $this->assertStringNotContainsString(')', $result);
    }

    public function testCssReturnsEmptyForEmptyString(): void
    {
        $helper = new HtmlHelper();

        $this->assertSame('', $helper->css(''));
    }

    // ---------------------------------------------------------------
    // nonce() and classIf() — existing methods
    // ---------------------------------------------------------------

    public function testNonceReturnsBase64String(): void
    {
        $helper = new HtmlHelper();

        $result = $helper->nonce();

        $this->assertSame(24, strlen($result));
        $this->assertNotFalse(base64_decode($result, true));
    }

    public function testNonceIsUniquePerCall(): void
    {
        $helper = new HtmlHelper();

        $this->assertNotSame($helper->nonce(), $helper->nonce());
    }

    public function testClassIfTrueReturnsClass(): void
    {
        $helper = new HtmlHelper();

        $this->assertSame('selected', $helper->classIf(true, 'selected'));
    }

    public function testClassIfFalseReturnsEmpty(): void
    {
        $helper = new HtmlHelper();

        $this->assertSame('', $helper->classIf(false, 'selected'));
    }
}
