<?php

declare(strict_types=1);

namespace Arcanum\Test\Shodo;

use Arcanum\Shodo\TemplateCompiler;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(TemplateCompiler::class)]
final class TemplateCompilerTest extends TestCase
{
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
        $this->assertStringContainsString('<?php foreach($items as $item): ?>', $result);
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
        $this->assertStringContainsString('<?php if($show): ?>', $result);
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
            '<?php if($a): ?>A<?php elseif($b): ?>B<?php else: ?>C<?php endif; ?>',
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
        $this->assertStringContainsString('<?php for($i = 0; $i < 3; $i++): ?>', $result);
        $this->assertStringContainsString('<?php endfor; ?>', $result);
    }

    public function testWhileDirective(): void
    {
        // Arrange
        $compiler = new TemplateCompiler();

        // Act
        $result = $compiler->compile('{{ while($running) }}go{{ endwhile }}');

        // Assert
        $this->assertStringContainsString('<?php while($running): ?>', $result);
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
        $expected = '<?php foreach($rows as $row): ?>'
            . '<?php if($row): ?>'
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
        $this->assertStringContainsString('<?php foreach($items as $key => $value): ?>', $result);
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
        $this->assertStringContainsString('<?php foreach($products as $product): ?>', $result);
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
}
