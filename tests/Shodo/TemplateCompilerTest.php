<?php

declare(strict_types=1);

namespace Arcanum\Test\Shodo;

use Arcanum\Shodo\TemplateCompiler;
use Arcanum\Parchment\Reader;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

#[CoversClass(TemplateCompiler::class)]
#[UsesClass(Reader::class)]
final class TemplateCompilerTest extends TestCase
{
    private static string $fixtureDir;

    public static function setUpBeforeClass(): void
    {
        self::$fixtureDir = dirname(__DIR__)
            . '/Fixture/Templates';
    }

    public function testEscapedOutput(): void
    {
        // Arrange
        $compiler = new TemplateCompiler();

        // Act
        $result = $compiler->compile('<p>{{ $name }}</p>');

        // Assert
        $this->assertSame(
            '<p><?= $__escape((string)($name)) ?></p>',
            $result,
        );
    }

    public function testRawOutput(): void
    {
        // Arrange
        $compiler = new TemplateCompiler();

        // Act
        $result = $compiler->compile('<div>{{! $html !}}</div>');

        // Assert
        $this->assertSame('<div><?= $html ?></div>', $result);
    }

    public function testRawOutputIsNotDoubleEscaped(): void
    {
        // Arrange
        $compiler = new TemplateCompiler();

        // Act
        $result = $compiler->compile('{{! $safe !}} and {{ $unsafe }}');

        // Assert
        $this->assertSame(
            '<?= $safe ?> and <?= $__escape((string)($unsafe)) ?>',
            $result,
        );
    }

    public function testForeachDirective(): void
    {
        // Arrange
        $compiler = new TemplateCompiler();

        // Act
        $result = $compiler->compile('{{ foreach($items as $item) }}<li>{{ $item }}</li>{{ endforeach }}');

        // Assert
        $this->assertStringContainsString('<?php foreach ($items as $item): ?>', $result);
        $this->assertStringContainsString('<?php endforeach; ?>', $result);
    }

    public function testForeachWithOptionalColon(): void
    {
        // Arrange
        $compiler = new TemplateCompiler();

        // Act
        $withColon = $compiler->compile('{{ foreach($items as $item): }}x{{ endforeach }}');
        $withoutColon = $compiler->compile('{{ foreach($items as $item) }}x{{ endforeach }}');

        // Assert
        $this->assertSame($withColon, $withoutColon);
    }

    public function testIfDirective(): void
    {
        // Arrange
        $compiler = new TemplateCompiler();

        // Act
        $result = $compiler->compile('{{ if($show) }}<p>yes</p>{{ endif }}');

        // Assert
        $this->assertStringContainsString('<?php if ($show): ?>', $result);
        $this->assertStringContainsString('<?php endif; ?>', $result);
    }

    public function testIfElseIfElse(): void
    {
        // Arrange
        $compiler = new TemplateCompiler();
        $template = '{{ if($a) }}A{{ elseif($b) }}B{{ else }}C{{ endif }}';

        // Act
        $result = $compiler->compile($template);

        // Assert
        $this->assertSame(
            '<?php if ($a): ?>A<?php elseif ($b): ?>B<?php else: ?>C<?php endif; ?>',
            $result,
        );
    }

    public function testIfWithOptionalColon(): void
    {
        // Arrange
        $compiler = new TemplateCompiler();

        // Act
        $withColon = $compiler->compile('{{ if($x): }}y{{ endif }}');
        $withoutColon = $compiler->compile('{{ if($x) }}y{{ endif }}');

        // Assert
        $this->assertSame($withColon, $withoutColon);
    }

    public function testForDirective(): void
    {
        // Arrange
        $compiler = new TemplateCompiler();

        // Act
        $result = $compiler->compile('{{ for($i = 0; $i < 3; $i++) }}{{ $i }}{{ endfor }}');

        // Assert
        $this->assertStringContainsString('<?php for ($i = 0; $i < 3; $i++): ?>', $result);
        $this->assertStringContainsString('<?php endfor; ?>', $result);
    }

    public function testWhileDirective(): void
    {
        // Arrange
        $compiler = new TemplateCompiler();

        // Act
        $result = $compiler->compile('{{ while($running) }}go{{ endwhile }}');

        // Assert
        $this->assertStringContainsString('<?php while ($running): ?>', $result);
        $this->assertStringContainsString('<?php endwhile; ?>', $result);
    }

    public function testEmptyTemplate(): void
    {
        // Arrange
        $compiler = new TemplateCompiler();

        // Act
        $result = $compiler->compile('');

        // Assert
        $this->assertSame('', $result);
    }

    public function testPlainHtmlPassesThrough(): void
    {
        // Arrange
        $compiler = new TemplateCompiler();
        $html = '<div class="test"><p>Hello world</p></div>';

        // Act
        $result = $compiler->compile($html);

        // Assert
        $this->assertSame($html, $result);
    }

    public function testNestedDirectives(): void
    {
        // Arrange
        $compiler = new TemplateCompiler();
        $template = '{{ foreach($rows as $row) }}{{ if($row) }}{{ $row }}{{ endif }}{{ endforeach }}';

        // Act
        $result = $compiler->compile($template);

        // Assert
        $expected = '<?php foreach ($rows as $row): ?>'
            . '<?php if ($row): ?>'
            . '<?= $__escape((string)($row)) ?>'
            . '<?php endif; ?>'
            . '<?php endforeach; ?>';
        $this->assertSame($expected, $result);
    }

    public function testWhitespaceInsideDelimitersIsTrimmed(): void
    {
        // Arrange
        $compiler = new TemplateCompiler();

        // Act
        $result = $compiler->compile('{{   $name   }}');

        // Assert
        $this->assertSame(
            '<?= $__escape((string)($name)) ?>',
            $result,
        );
    }

    public function testExpressionOutput(): void
    {
        // Arrange
        $compiler = new TemplateCompiler();

        // Act
        $result = $compiler->compile('{{ $item[\'name\'] }}');

        // Assert
        $this->assertSame(
            '<?= $__escape((string)($item[\'name\'])) ?>',
            $result,
        );
    }

    public function testForeachWithKeyValue(): void
    {
        // Arrange
        $compiler = new TemplateCompiler();

        // Act
        $result = $compiler->compile('{{ foreach($items as $key => $value) }}{{ $key }}{{ endforeach }}');

        // Assert
        $this->assertStringContainsString('<?php foreach ($items as $key => $value): ?>', $result);
    }

    public function testMixedContentCompilation(): void
    {
        // Arrange
        $compiler = new TemplateCompiler();
        $template = <<<'TPL'
            <!DOCTYPE html>
            <html>
            <body>
            {{ foreach($products as $product) }}
            <p>{{ $product['name'] }}</p>
            <div>{{! $product['description'] !}}</div>
            {{ endforeach }}
            </body>
            </html>
            TPL;

        // Act
        $result = $compiler->compile($template);

        // Assert
        $this->assertStringContainsString('<?php foreach ($products as $product): ?>', $result);
        $this->assertStringContainsString(
            '<?= $__escape((string)($product[\'name\'])) ?>',
            $result,
        );
        $this->assertStringContainsString('<?= $product[\'description\'] ?>', $result);
        $this->assertStringContainsString('<?php endforeach; ?>', $result);
    }

    public function testElseIfWithOptionalColon(): void
    {
        // Arrange
        $compiler = new TemplateCompiler();

        // Act
        $withColon = $compiler->compile('{{ if($a): }}A{{ elseif($b): }}B{{ endif }}');
        $withoutColon = $compiler->compile('{{ if($a) }}A{{ elseif($b) }}B{{ endif }}');

        // Assert
        $this->assertSame($withColon, $withoutColon);
    }

    // -----------------------------------------------------------
    // Tolerant control structure forms
    // -----------------------------------------------------------

    public function testIfAcceptsParenFreeForm(): void
    {
        // Arrange — preferred form: no parens, just a space and an expression
        $compiler = new TemplateCompiler();

        // Act
        $result = $compiler->compile('{{ if $foo > 0 }}yes{{ endif }}');

        // Assert
        $this->assertSame(
            '<?php if ($foo > 0): ?>yes<?php endif; ?>',
            $result,
        );
    }

    public function testIfThreeFormsCompileToSameOutput(): void
    {
        // Arrange — all three accepted forms must normalise to the same PHP
        $compiler = new TemplateCompiler();

        // Act
        $bare      = $compiler->compile('{{ if $foo > 0 }}A{{ endif }}');
        $parens    = $compiler->compile('{{ if ($foo > 0) }}A{{ endif }}');
        $altSyntax = $compiler->compile('{{ if ($foo > 0): }}A{{ endif }}');

        // Assert
        $this->assertSame($bare, $parens);
        $this->assertSame($bare, $altSyntax);
        $this->assertSame('<?php if ($foo > 0): ?>A<?php endif; ?>', $bare);
    }

    public function testIfPreservesInternalParensInExpression(): void
    {
        // Arrange — outer-paren stripping must NOT misfire on expressions
        // like `(a) || (b)` where the first `(` and last `)` aren't a pair.
        $compiler = new TemplateCompiler();

        // Act
        $result = $compiler->compile('{{ if ($a > 0) || ($b < 5) }}x{{ endif }}');

        // Assert — both groups preserved
        $this->assertSame(
            '<?php if (($a > 0) || ($b < 5)): ?>x<?php endif; ?>',
            $result,
        );
    }

    public function testForeachAcceptsParenFreeForm(): void
    {
        // Arrange
        $compiler = new TemplateCompiler();

        // Act
        $result = $compiler->compile('{{ foreach $items as $item }}{{ $item }}{{ endforeach }}');

        // Assert
        $this->assertSame(
            '<?php foreach ($items as $item): ?><?= $__escape((string)($item)) ?><?php endforeach; ?>',
            $result,
        );
    }

    // -----------------------------------------------------------
    // match / case / default / endmatch
    // -----------------------------------------------------------

    public function testMatchCompilesToSwitch(): void
    {
        // Arrange
        $compiler = new TemplateCompiler();
        $source = "{{ match \$status }}{{ case 'open' }}A{{ case 'closed' }}B{{ endmatch }}";

        // Act
        $result = $compiler->compile($source);

        // Assert
        $this->assertStringContainsString('<?php switch ($status): ?>', $result);
        $this->assertStringContainsString("<?php case 'open': ?>A<?php break; ?>", $result);
        $this->assertStringContainsString("<?php case 'closed': ?>B<?php break; ?>", $result);
        $this->assertStringContainsString('<?php endswitch; ?>', $result);
    }

    public function testMatchSupportsCommaSeparatedCases(): void
    {
        // Arrange — multiple values in a single case become PHP fall-through
        $compiler = new TemplateCompiler();
        $source = "{{ match \$status }}{{ case 'pending', 'active' }}live{{ endmatch }}";

        // Act
        $result = $compiler->compile($source);

        // Assert — both values emit independent case statements that share the body
        $expected = "<?php case 'pending': ?><?php case 'active': ?>live<?php break; ?>";
        $this->assertStringContainsString($expected, $result);
    }

    public function testMatchDefaultCase(): void
    {
        // Arrange
        $compiler = new TemplateCompiler();
        $source = "{{ match \$status }}{{ case 'open' }}A{{ default }}other{{ endmatch }}";

        // Act
        $result = $compiler->compile($source);

        // Assert
        $this->assertStringContainsString('<?php default: ?>other<?php break; ?>', $result);
    }

    public function testMatchWithNoCasesProducesEmptySwitch(): void
    {
        // Arrange
        $compiler = new TemplateCompiler();
        $source = "{{ match \$status }}{{ endmatch }}";

        // Act
        $result = $compiler->compile($source);

        // Assert
        $this->assertSame('<?php switch ($status): ?><?php endswitch; ?>', $result);
    }

    public function testMatchCaseBodyContainsTemplateSyntaxThatStillCompiles(): void
    {
        // Arrange — case bodies are template source, so {{ $var }} inside
        // a case should compile through the normal escape pass.
        $compiler = new TemplateCompiler();
        $source = "{{ match \$role }}{{ case 'admin' }}{{ \$user->name }}{{ endmatch }}";

        // Act
        $result = $compiler->compile($source);

        // Assert
        $this->assertStringContainsString('<?= $__escape((string)($user->name)) ?>', $result);
    }

    public function testMatchSplitsCommaCasesRespectingStrings(): void
    {
        // Arrange — comma inside a string literal must NOT split the case
        $compiler = new TemplateCompiler();
        $source = "{{ match \$x }}{{ case 'a, b', 'c' }}body{{ endmatch }}";

        // Act
        $result = $compiler->compile($source);

        // Assert — two cases: 'a, b' and 'c'
        $this->assertStringContainsString("<?php case 'a, b': ?><?php case 'c': ?>body<?php break; ?>", $result);
    }

    public function testMatchExpressionEvaluatedOnce(): void
    {
        // Arrange — the match subject appears exactly once in the output
        $compiler = new TemplateCompiler();
        $source = "{{ match func() }}{{ case 1 }}A{{ case 2 }}B{{ endmatch }}";

        // Act
        $result = $compiler->compile($source);

        // Assert
        $this->assertSame(1, substr_count($result, 'switch (func())'));
    }

    // -----------------------------------------------------------
    // render() — direct substitution for stubs
    // -----------------------------------------------------------

    public function testRenderReplacesRawPlaceholders(): void
    {
        $compiler = new TemplateCompiler();

        $result = $compiler->render(
            'Hello {{! $name !}}, you are {{! $age !}}.',
            ['name' => 'Alice', 'age' => '30'],
        );

        $this->assertSame('Hello Alice, you are 30.', $result);
    }

    public function testRenderPreservesPhpSourceCode(): void
    {
        $compiler = new TemplateCompiler();
        $stub = "<?php\n\nnamespace {{! \$namespace !}};\n\nfinal class {{! \$className !}}\n{\n}\n";

        $result = $compiler->render($stub, [
            'namespace' => 'App\\Domain\\Command',
            'className' => 'Submit',
        ]);

        $this->assertStringContainsString('<?php', $result);
        $this->assertStringContainsString('namespace App\\Domain\\Command;', $result);
        $this->assertStringContainsString('final class Submit', $result);
    }

    public function testRenderLeavesUnmatchedPlaceholders(): void
    {
        $compiler = new TemplateCompiler();

        $result = $compiler->render('{{! $known !}} and {{! $unknown !}}', ['known' => 'yes']);

        $this->assertSame('yes and {{! $unknown !}}', $result);
    }

    public function testRenderWithEmptyVariables(): void
    {
        $compiler = new TemplateCompiler();

        $result = $compiler->render('no placeholders here', []);

        $this->assertSame('no placeholders here', $result);
    }

    // -----------------------------------------------------------
    // Helper call compilation: {{ Name::method() }} syntax
    // -----------------------------------------------------------

    public function testHelperCallCompilesWithEscape(): void
    {
        // Arrange
        $compiler = new TemplateCompiler();

        // Act
        $result = $compiler->compile('{{ Route::url(\'query:health\') }}');

        // Assert
        $this->assertSame(
            '<?= $__escape((string)($__helpers[\'Route\']->url(\'query:health\'))) ?>',
            $result,
        );
    }

    public function testHelperCallWithNoArgs(): void
    {
        // Arrange
        $compiler = new TemplateCompiler();

        // Act
        $result = $compiler->compile('{{ Html::csrf() }}');

        // Assert
        $this->assertSame(
            '<?= $__escape((string)($__helpers[\'Html\']->csrf())) ?>',
            $result,
        );
    }

    public function testHelperCallWithMultipleArgs(): void
    {
        // Arrange
        $compiler = new TemplateCompiler();

        // Act
        $result = $compiler->compile('{{ Format::number($price, 2, \'.\', \',\') }}');

        // Assert
        $this->assertSame(
            '<?= $__escape((string)($__helpers[\'Format\']->number($price, 2, \'.\', \',\'))) ?>',
            $result,
        );
    }

    public function testHelperCallWithNestedExpression(): void
    {
        // Arrange
        $compiler = new TemplateCompiler();

        // Act
        $result = $compiler->compile('{{ Format::number($item->price, 2) }}');

        // Assert
        $this->assertSame(
            '<?= $__escape((string)($__helpers[\'Format\']->number($item->price, 2))) ?>',
            $result,
        );
    }

    public function testHelperCallInRawOutput(): void
    {
        // Arrange
        $compiler = new TemplateCompiler();

        // Act
        $result = $compiler->compile('{{! Html::csrf() !}}');

        // Assert
        $this->assertSame(
            '<?= $__helpers[\'Html\']->csrf() ?>',
            $result,
        );
    }

    public function testRegularVariableStillCompilesNormally(): void
    {
        // Arrange
        $compiler = new TemplateCompiler();

        // Act
        $result = $compiler->compile('{{ $name }} and {{ Route::url(\'x\') }}');

        // Assert
        $this->assertSame(
            '<?= $__escape((string)($name)) ?> and <?= $__escape((string)($__helpers[\'Route\']->url(\'x\'))) ?>',
            $result,
        );
    }

    public function testFullyQualifiedStaticCallIsLeftAlone(): void
    {
        // Arrange
        $compiler = new TemplateCompiler();

        // Act — backslash in class name means it's a real static call, not a helper
        $result = $compiler->compile('{{ \App\Foo::bar() }}');

        // Assert — compiled as a regular expression, not rewritten to $__helpers
        $this->assertSame(
            '<?= $__escape((string)(\App\Foo::bar())) ?>',
            $result,
        );
    }

    public function testHelperCallWithArrayAccess(): void
    {
        // Arrange — the case that originally surfaced the rewrite need.
        $compiler = new TemplateCompiler();

        // Act
        $result = $compiler->compile('{{ Tip::today()[\'title\'] }}');

        // Assert
        $this->assertSame(
            '<?= $__escape((string)($__helpers[\'Tip\']->today()[\'title\'])) ?>',
            $result,
        );
    }

    public function testHelperCallWithMethodChain(): void
    {
        // Arrange
        $compiler = new TemplateCompiler();

        // Act
        $result = $compiler->compile('{{ User::current()->name }}');

        // Assert
        $this->assertSame(
            '<?= $__escape((string)($__helpers[\'User\']->current()->name)) ?>',
            $result,
        );
    }

    public function testHelperCallInArithmetic(): void
    {
        // Arrange
        $compiler = new TemplateCompiler();

        // Act
        $result = $compiler->compile('{{ Math::pi() + 1 }}');

        // Assert
        $this->assertSame(
            '<?= $__escape((string)($__helpers[\'Math\']->pi() + 1)) ?>',
            $result,
        );
    }

    public function testHelperCallInTernary(): void
    {
        // Arrange
        $compiler = new TemplateCompiler();

        // Act
        $result = $compiler->compile('{{ Env::debugMode() ? \'on\' : \'off\' }}');

        // Assert
        $this->assertSame(
            '<?= $__escape((string)($__helpers[\'Env\']->debugMode() ? \'on\' : \'off\')) ?>',
            $result,
        );
    }

    public function testHelperCallWithNullCoalesce(): void
    {
        // Arrange
        $compiler = new TemplateCompiler();

        // Act
        $result = $compiler->compile('{{ User::current() ?? \'guest\' }}');

        // Assert
        $this->assertSame(
            '<?= $__escape((string)($__helpers[\'User\']->current() ?? \'guest\')) ?>',
            $result,
        );
    }

    public function testNestedHelperCallsInOneExpression(): void
    {
        // Arrange — both helper occurrences rewrite in a single pass.
        $compiler = new TemplateCompiler();

        // Act
        $result = $compiler->compile('{{ Format::number(Math::pi(), 2) }}');

        // Assert
        $this->assertSame(
            '<?= $__escape((string)($__helpers[\'Format\']->number($__helpers[\'Math\']->pi(), 2))) ?>',
            $result,
        );
    }

    public function testHelperCallFollowedByConcatenation(): void
    {
        // Arrange
        $compiler = new TemplateCompiler();

        // Act
        $result = $compiler->compile('{{ Str::upper($name) . \'!\' }}');

        // Assert
        $this->assertSame(
            '<?= $__escape((string)($__helpers[\'Str\']->upper($name) . \'!\')) ?>',
            $result,
        );
    }

    public function testHelperCallInIfCondition(): void
    {
        // Arrange — previously a silent bug: control-structure expressions
        // were not run through the helper rewriter, so this compiled to a
        // literal PHP static call to a class named Env.
        $compiler = new TemplateCompiler();

        // Act
        $result = $compiler->compile('{{ if Env::debugMode() }}on{{ endif }}');

        // Assert
        $this->assertSame(
            '<?php if ($__helpers[\'Env\']->debugMode()): ?>on<?php endif; ?>',
            $result,
        );
    }

    public function testHelperCallInForeachExpression(): void
    {
        // Arrange
        $compiler = new TemplateCompiler();

        // Act
        $result = $compiler->compile('{{ foreach Wired::list() as $item }}{{ $item }}{{ endforeach }}');

        // Assert
        $this->assertSame(
            '<?php foreach ($__helpers[\'Wired\']->list() as $item): ?>'
            . '<?= $__escape((string)($item)) ?>'
            . '<?php endforeach; ?>',
            $result,
        );
    }

    public function testVariableStaticCallIsLeftAlone(): void
    {
        // Arrange — `$Foo::method()` is a variable static call, not a helper.
        // The lookbehind in HELPER_CALL_PATTERN excludes a preceding `$`.
        $compiler = new TemplateCompiler();

        // Act
        $result = $compiler->compile('{{ $Foo::method() }}');

        // Assert
        $this->assertSame(
            '<?= $__escape((string)($Foo::method())) ?>',
            $result,
        );
    }

    public function testPartiallyQualifiedStaticCallIsLeftAlone(): void
    {
        // Arrange — preceded by `\`, so the lookbehind blocks the rewrite.
        $compiler = new TemplateCompiler();

        // Act
        $result = $compiler->compile('{{ App\Foo::bar() }}');

        // Assert
        $this->assertSame(
            '<?= $__escape((string)(App\Foo::bar())) ?>',
            $result,
        );
    }

    public function testHelperCallWithNestedParentheses(): void
    {
        // Arrange
        $compiler = new TemplateCompiler();

        // Act
        $result = $compiler->compile('{{ Format::number(count($items), 0) }}');

        // Assert
        $this->assertSame(
            '<?= $__escape((string)($__helpers[\'Format\']->number(count($items), 0))) ?>',
            $result,
        );
    }

    // -----------------------------------------------------------
    // csrf directive
    // -----------------------------------------------------------

    public function testCsrfDirectiveCompilesAsRawHelperCall(): void
    {
        // Arrange
        $compiler = new TemplateCompiler();

        // Act
        $result = $compiler->compile('{{ csrf }}');

        // Assert — raw output, no $__escape
        $this->assertSame(
            '<?= $__helpers[\'Csrf\']->field() ?>',
            $result,
        );
    }

    public function testCsrfDirectiveWithSurroundingContent(): void
    {
        // Arrange
        $compiler = new TemplateCompiler();

        // Act
        $result = $compiler->compile('<form>{{ csrf }}<button>Submit</button></form>');

        // Assert
        $this->assertSame(
            '<form><?= $__helpers[\'Csrf\']->field() ?><button>Submit</button></form>',
            $result,
        );
    }

    // -----------------------------------------------------------
    // include directive
    // -----------------------------------------------------------

    public function testIncludeInlinesFileContents(): void
    {
        // Arrange
        $compiler = new TemplateCompiler();
        $source = "<div>{{ include 'partials/nav' }}</div>";

        // Act
        $result = $compiler->compile($source, self::$fixtureDir);

        // Assert — nav.html content is inlined, then compiled
        $this->assertStringContainsString('<nav>Navigation</nav>', $result);
    }

    public function testIncludeResolvesWithoutExtension(): void
    {
        // Arrange
        $compiler = new TemplateCompiler();
        $source = "{{ include 'partials/nav' }}";

        // Act
        $result = $compiler->compile($source, self::$fixtureDir);

        // Assert
        $this->assertStringContainsString('<nav>Navigation</nav>', $result);
    }

    public function testIncludeResolvesWithExtension(): void
    {
        // Arrange
        $compiler = new TemplateCompiler();
        $source = "{{ include 'partials/nav.html' }}";

        // Act
        $result = $compiler->compile($source, self::$fixtureDir);

        // Assert
        $this->assertStringContainsString('<nav>Navigation</nav>', $result);
    }

    public function testIncludeWithSurroundingContent(): void
    {
        // Arrange
        $compiler = new TemplateCompiler();
        $source = file_get_contents(self::$fixtureDir . '/page-with-include.html');
        assert(is_string($source));

        // Act
        $result = $compiler->compile($source, self::$fixtureDir);

        // Assert
        $this->assertStringContainsString('<nav>Navigation</nav>', $result);
        $this->assertStringContainsString('$__escape((string)($message))', $result);
    }

    public function testIncludeThrowsForMissingFile(): void
    {
        // Arrange
        $compiler = new TemplateCompiler();
        $source = "{{ include 'partials/nonexistent' }}";

        // Assert
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Include file not found');

        // Act
        $compiler->compile($source, self::$fixtureDir);
    }

    // -----------------------------------------------------------
    // extends / section / yield — layout inheritance
    // -----------------------------------------------------------

    public function testLayoutInheritance(): void
    {
        // Arrange
        $compiler = new TemplateCompiler();
        $source = file_get_contents(self::$fixtureDir . '/page-with-layout.html');
        assert(is_string($source));

        // Act
        $result = $compiler->compile($source, self::$fixtureDir);

        // Assert — layout structure is present
        $this->assertStringContainsString('<!DOCTYPE html>', $result);
        $this->assertStringContainsString('<html>', $result);

        // Assert — title section was filled
        $this->assertStringContainsString('<title>My Page</title>', $result);

        // Assert — content section was filled and compiled
        $this->assertStringContainsString('$__escape((string)($message))', $result);

        // Assert — includes in the layout were resolved
        $this->assertStringContainsString('<nav>Navigation</nav>', $result);
        $this->assertStringContainsString('<footer>Footer</footer>', $result);
    }

    public function testLayoutResolvesFromSameDirectory(): void
    {
        // Arrange — Pages/nested-page.html extends 'layout', finds Pages/layout.html
        $compiler = new TemplateCompiler(
            templatesDirectory: self::$fixtureDir,
        );
        $pagesDir = self::$fixtureDir . '/Pages';
        $source = file_get_contents($pagesDir . '/nested-page.html');
        assert(is_string($source));

        // Act — co-located Pages/layout.html takes precedence over Templates/layout.html
        $result = $compiler->compile($source, $pagesDir);

        // Assert — uses the Pages/layout.html (has " - Pages" suffix)
        $this->assertStringContainsString('<title>Nested - Pages</title>', $result);
        $this->assertStringContainsString('Nested content', $result);
    }

    public function testLayoutResolvesFromTemplatesDirectory(): void
    {
        // Arrange — page-with-layout.html extends 'layout', finds it in
        // the configured templates directory
        $compiler = new TemplateCompiler(
            templatesDirectory: self::$fixtureDir,
        );
        $source = file_get_contents(self::$fixtureDir . '/page-with-layout.html');
        assert(is_string($source));

        // Act
        $result = $compiler->compile($source, self::$fixtureDir);

        // Assert — uses Templates/layout.html (no " - Pages" suffix)
        $this->assertStringContainsString('<title>My Page</title>', $result);
    }

    public function testEmptyYieldProducesEmptyString(): void
    {
        // Arrange — page that only fills 'content', not 'title'
        $compiler = new TemplateCompiler();
        $source = "{{ extends 'layout' }}\n{{ section 'content' }}Hello{{ endsection }}";

        // Act
        $result = $compiler->compile($source, self::$fixtureDir);

        // Assert — title yield is empty
        $this->assertStringContainsString('<title></title>', $result);
        $this->assertStringContainsString('Hello', $result);
    }

    public function testLayoutThrowsForMissingFile(): void
    {
        // Arrange
        $compiler = new TemplateCompiler();
        $source = "{{ extends 'nonexistent' }}\n{{ section 'content' }}y{{ endsection }}";

        // Assert
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Layout file not found');

        // Act
        $compiler->compile($source, self::$fixtureDir);
    }

    public function testNoExtendsPassesThroughUnchanged(): void
    {
        // Arrange
        $compiler = new TemplateCompiler();
        $source = '<p>{{ $name }}</p>';

        // Act — with a templateDirectory but no extends
        $result = $compiler->compile($source, self::$fixtureDir);

        // Assert — compiled normally, no layout wrapping
        $this->assertSame(
            '<p><?= $__escape((string)($name)) ?></p>',
            $result,
        );
    }

    public function testSectionMismatchThrowsWithAvailableYields(): void
    {
        // Arrange — child defines 'contnent' (typo) but layout has 'content'
        $compiler = new TemplateCompiler(
            templatesDirectory: self::$fixtureDir,
        );
        $source = "{{ extends 'layout' }}\n"
            . "{{ section 'title' }}OK{{ endsection }}\n"
            . "{{ section 'contnent' }}Typo{{ endsection }}";

        // Assert
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('contnent');
        $this->expectExceptionMessage('Available yields');

        // Act
        $compiler->compile($source, self::$fixtureDir);
    }

    public function testCompileWithoutDirectorySkipsPreCompilation(): void
    {
        // Arrange
        $compiler = new TemplateCompiler();
        $source = "{{ include 'partials/nav' }}";

        // Act — no directory, so include is treated as unknown and compiled
        // as a regular expression (which will be an escaped output)
        $result = $compiler->compile($source);

        // Assert — include was NOT resolved (no directory to resolve against)
        $this->assertStringNotContainsString('<nav>', $result);
    }

    // -----------------------------------------------------------
    // Fragment rendering (htmx partial swaps)
    // -----------------------------------------------------------

    public function testFragmentModeRendersOnlyContentSection(): void
    {
        // Arrange
        $compiler = new TemplateCompiler();
        $source = file_get_contents(self::$fixtureDir . '/page-with-layout.html');
        assert(is_string($source));

        // Act
        $result = $compiler->compileFragment($source, self::$fixtureDir);

        // Assert — only the content section, no layout wrapper
        $this->assertStringNotContainsString('<!DOCTYPE html>', $result);
        $this->assertStringNotContainsString('<nav>', $result);
        $this->assertStringNotContainsString('<footer>', $result);
        $this->assertStringContainsString('$__escape((string)($message))', $result);
    }

    public function testFragmentModeWithNoExtendsPassesThrough(): void
    {
        // Arrange — template without extends
        $compiler = new TemplateCompiler();
        $source = '<p>{{ $name }}</p>';

        // Act
        $result = $compiler->compileFragment($source, self::$fixtureDir);

        // Assert — compiled normally since there's no layout to strip
        $this->assertSame(
            '<p><?= $__escape((string)($name)) ?></p>',
            $result,
        );
    }

    public function testFragmentModeReturnsEmptyWhenNoContentSection(): void
    {
        // Arrange — extends layout but only fills 'title', not 'content'
        $compiler = new TemplateCompiler();
        $source = "{{ extends 'layout' }}\n{{ section 'title' }}Title{{ endsection }}";

        // Act
        $result = $compiler->compileFragment($source, self::$fixtureDir);

        // Assert — no content section defined, so empty
        $this->assertSame('', $result);
    }

    // ------------------------------------------------------------------
    // Dependency tracking
    // ------------------------------------------------------------------

    public function testLastDependenciesIsEmptyWhenNoIncludesOrLayout(): void
    {
        // Arrange
        $compiler = new TemplateCompiler();

        // Act
        $compiler->compile('<p>{{ $name }}</p>', self::$fixtureDir);

        // Assert
        $this->assertSame([], $compiler->lastDependencies());
    }

    public function testLastDependenciesTracksIncludeFile(): void
    {
        // Arrange
        $compiler = new TemplateCompiler();
        $source = file_get_contents(self::$fixtureDir . '/page-with-include.html');
        assert(is_string($source));

        // Act
        $compiler->compile($source, self::$fixtureDir);

        // Assert — the included partial is tracked
        $deps = $compiler->lastDependencies();
        $this->assertCount(1, $deps);
        $this->assertStringEndsWith('partials/nav.html', $deps[0]);
    }

    public function testLastDependenciesTracksLayoutAndItsIncludes(): void
    {
        // Arrange — page-with-layout extends layout, which includes nav + footer
        $compiler = new TemplateCompiler();
        $source = file_get_contents(self::$fixtureDir . '/page-with-layout.html');
        assert(is_string($source));

        // Act
        $compiler->compile($source, self::$fixtureDir);

        // Assert — layout, nav, and footer are all tracked
        $deps = $compiler->lastDependencies();
        $this->assertCount(3, $deps);

        $depNames = array_map(fn(string $p) => basename($p), $deps);
        $this->assertContains('layout.html', $depNames);
        $this->assertContains('nav.html', $depNames);
        $this->assertContains('footer.html', $depNames);
    }

    public function testLastDependenciesResetsBetweenCompileCalls(): void
    {
        // Arrange
        $compiler = new TemplateCompiler();
        $withDeps = file_get_contents(self::$fixtureDir . '/page-with-include.html');
        assert(is_string($withDeps));

        // Act — first compile produces deps, second should reset
        $compiler->compile($withDeps, self::$fixtureDir);
        $this->assertNotEmpty($compiler->lastDependencies());

        $compiler->compile('<p>plain</p>', self::$fixtureDir);

        // Assert
        $this->assertSame([], $compiler->lastDependencies());
    }

    public function testLastDependenciesAreDeduplicated(): void
    {
        // Arrange — a template that includes the same partial twice should
        // only have one entry for it.
        $compiler = new TemplateCompiler();
        $source = "{{ include 'partials/nav' }}\n{{ include 'partials/nav' }}";

        // Act
        $compiler->compile($source, self::$fixtureDir);

        // Assert
        $deps = $compiler->lastDependencies();
        $this->assertCount(1, $deps);
    }
}
