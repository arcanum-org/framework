<?php

declare(strict_types=1);

namespace Arcanum\Test\Hyper;

use Arcanum\Flow\River\LazyResource;
use Arcanum\Flow\River\Stream;
use Arcanum\Flow\River\StreamResource;
use Arcanum\Gather\IgnoreCaseRegistry;
use Arcanum\Gather\Registry;
use Arcanum\Glitch\ArcanumException;
use Arcanum\Glitch\HttpException;
use Arcanum\Hyper\Headers;
use Arcanum\Hyper\HtmlExceptionResponseRenderer;
use Arcanum\Parchment\Reader;
use Arcanum\Parchment\Writer;
use Arcanum\Shodo\TemplateCache;
use Arcanum\Shodo\TemplateCompiler;
use Arcanum\Shodo\TemplateEngine;
use Arcanum\Shodo\TemplateResolver;
use Arcanum\Validation\ValidationException;
use Arcanum\Validation\ValidationError;
use Arcanum\Hyper\Message;
use Arcanum\Hyper\Phrase;
use Arcanum\Hyper\Response;
use Arcanum\Hyper\StatusCode;
use Arcanum\Hyper\Version;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Psr\Http\Message\ResponseInterface;

#[CoversClass(HtmlExceptionResponseRenderer::class)]
#[UsesClass(ArcanumException::class)]
#[UsesClass(HttpException::class)]
#[UsesClass(TemplateCompiler::class)]
#[UsesClass(TemplateEngine::class)]
#[UsesClass(TemplateCache::class)]
#[UsesClass(Reader::class)]
#[UsesClass(Writer::class)]
#[UsesClass(Response::class)]
#[UsesClass(Message::class)]
#[UsesClass(Headers::class)]
#[UsesClass(StatusCode::class)]
#[UsesClass(Phrase::class)]
#[UsesClass(Version::class)]
#[UsesClass(Stream::class)]
#[UsesClass(LazyResource::class)]
#[UsesClass(StreamResource::class)]
#[UsesClass(IgnoreCaseRegistry::class)]
#[UsesClass(Registry::class)]
#[UsesClass(TemplateResolver::class)]
#[UsesClass(ValidationException::class)]
#[UsesClass(ValidationError::class)]
#[UsesClass(\Arcanum\Parchment\FileSystem::class)]
final class HtmlExceptionResponseRendererTest extends TestCase
{
    private function getBody(ResponseInterface $response): string
    {
        $body = $response->getBody();
        $body->rewind();
        return $body->getContents();
    }

    // -----------------------------------------------------------
    // Response structure
    // -----------------------------------------------------------

    public function testRenderReturnsResponseInterface(): void
    {
        // Arrange
        $renderer = new HtmlExceptionResponseRenderer();

        // Act
        $response = $renderer->render(new \RuntimeException('fail'));

        // Assert
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testRenderSetsHtmlContentType(): void
    {
        // Arrange
        $renderer = new HtmlExceptionResponseRenderer();

        // Act
        $response = $renderer->render(new \RuntimeException('fail'));

        // Assert
        $this->assertSame('text/html; charset=UTF-8', $response->getHeaderLine('Content-Type'));
    }

    public function testRenderSetsContentLength(): void
    {
        // Arrange
        $renderer = new HtmlExceptionResponseRenderer();

        // Act
        $response = $renderer->render(new \RuntimeException('fail'));
        $body = $this->getBody($response);

        // Assert
        $this->assertSame((string) strlen($body), $response->getHeaderLine('Content-Length'));
    }

    // -----------------------------------------------------------
    // Status code mapping
    // -----------------------------------------------------------

    public function testRenderUsesStatusCodeFromHttpException(): void
    {
        // Arrange
        $renderer = new HtmlExceptionResponseRenderer();

        // Act
        $response = $renderer->render(new HttpException(StatusCode::NotFound));

        // Assert
        $this->assertSame(404, $response->getStatusCode());
    }

    public function testRenderDefaultsToInternalServerErrorForGenericExceptions(): void
    {
        // Arrange
        $renderer = new HtmlExceptionResponseRenderer();

        // Act
        $response = $renderer->render(new \RuntimeException('unexpected'));

        // Assert
        $this->assertSame(500, $response->getStatusCode());
    }

    // -----------------------------------------------------------
    // HTML content — production mode
    // -----------------------------------------------------------

    public function testRenderIncludesStatusCodeInBody(): void
    {
        // Arrange
        $renderer = new HtmlExceptionResponseRenderer();

        // Act
        $body = $this->getBody($renderer->render(new HttpException(StatusCode::NotFound)));

        // Assert
        $this->assertStringContainsString('404', $body);
    }

    public function testRenderIncludesTitleInBody(): void
    {
        // Arrange
        $renderer = new HtmlExceptionResponseRenderer();

        // Act
        $body = $this->getBody($renderer->render(new HttpException(StatusCode::NotFound)));

        // Assert
        $this->assertStringContainsString('Not Found', $body);
    }

    public function testRenderIncludesMessageInBody(): void
    {
        // Arrange
        $renderer = new HtmlExceptionResponseRenderer();

        // Act
        $body = $this->getBody($renderer->render(new HttpException(StatusCode::NotFound, 'Order #42 not found')));

        // Assert
        $this->assertStringContainsString('Order #42 not found', $body);
    }

    public function testRenderIncludesNavigationLinks(): void
    {
        // Arrange
        $renderer = new HtmlExceptionResponseRenderer();

        // Act
        $body = $this->getBody($renderer->render(new \RuntimeException('fail')));

        // Assert
        $this->assertStringContainsString('Go back', $body);
        $this->assertStringContainsString('Go home', $body);
    }

    public function testRenderUsesReasonPhraseForGenericExceptions(): void
    {
        // Arrange
        $renderer = new HtmlExceptionResponseRenderer();

        // Act
        $body = $this->getBody($renderer->render(new \RuntimeException('fail')));

        // Assert
        $this->assertStringContainsString('Internal Server Error', $body);
    }

    public function testRenderEscapesHtmlInMessage(): void
    {
        // Arrange
        $renderer = new HtmlExceptionResponseRenderer();

        // Act
        $body = $this->getBody($renderer->render(new \RuntimeException('<script>alert(1)</script>')));

        // Assert
        $this->assertStringNotContainsString('<script>', $body);
        $this->assertStringContainsString('&lt;script&gt;', $body);
    }

    public function testRenderExcludesDebugInfoInProduction(): void
    {
        // Arrange
        $renderer = new HtmlExceptionResponseRenderer(debug: false);

        // Act
        $body = $this->getBody($renderer->render(new \RuntimeException('fail')));

        // Assert
        $this->assertStringNotContainsString('Stack trace', $body);
        $this->assertStringNotContainsString('RuntimeException', $body);
    }

    // -----------------------------------------------------------
    // HTML content — debug mode
    // -----------------------------------------------------------

    public function testRenderDebugIncludesExceptionClass(): void
    {
        // Arrange
        $renderer = new HtmlExceptionResponseRenderer(debug: true);

        // Act
        $body = $this->getBody($renderer->render(new \RuntimeException('fail')));

        // Assert
        $this->assertStringContainsString('RuntimeException', $body);
    }

    public function testRenderDebugIncludesFileAndLine(): void
    {
        // Arrange
        $renderer = new HtmlExceptionResponseRenderer(debug: true);
        $exception = new \RuntimeException('fail');

        // Act
        $body = $this->getBody($renderer->render($exception));

        // Assert
        $this->assertStringContainsString(basename(__FILE__), $body);
        $this->assertStringContainsString((string) $exception->getLine(), $body);
    }

    public function testRenderDebugIncludesStackTrace(): void
    {
        // Arrange
        $renderer = new HtmlExceptionResponseRenderer(debug: true);

        // Act
        $body = $this->getBody($renderer->render(new \RuntimeException('fail')));

        // Assert
        $this->assertStringContainsString('Stack trace', $body);
    }

    // -----------------------------------------------------------
    // ArcanumException — title
    // -----------------------------------------------------------

    public function testRenderUsesArcanumExceptionTitle(): void
    {
        // Arrange
        $renderer = new HtmlExceptionResponseRenderer();

        // Act — HttpException with custom message but title is always the reason phrase
        $body = $this->getBody($renderer->render(new HttpException(StatusCode::Forbidden, 'Access denied')));

        // Assert
        $this->assertStringContainsString('Forbidden', $body);
    }

    // -----------------------------------------------------------
    // ArcanumException — suggestion
    // -----------------------------------------------------------

    public function testRenderIncludesSuggestionWhenVerboseErrorsEnabled(): void
    {
        // Arrange
        $renderer = new HtmlExceptionResponseRenderer(verboseErrors: true);
        $exception = (new HttpException(StatusCode::NotFound, 'Order not found'))
            ->withSuggestion('Check the order ID and try again');

        // Act
        $body = $this->getBody($renderer->render($exception));

        // Assert
        $this->assertStringContainsString('Check the order ID and try again', $body);
    }

    public function testRenderExcludesSuggestionWhenVerboseErrorsDisabled(): void
    {
        // Arrange
        $renderer = new HtmlExceptionResponseRenderer(verboseErrors: false);
        $exception = (new HttpException(StatusCode::NotFound, 'Order not found'))
            ->withSuggestion('Check the order ID and try again');

        // Act
        $body = $this->getBody($renderer->render($exception));

        // Assert
        $this->assertStringNotContainsString('Check the order ID and try again', $body);
    }

    public function testRenderExcludesSuggestionWhenNullEvenIfVerbose(): void
    {
        // Arrange
        $renderer = new HtmlExceptionResponseRenderer(verboseErrors: true);

        // Act
        $body = $this->getBody($renderer->render(new HttpException(StatusCode::NotFound)));

        // Assert — no suggestion element present
        $this->assertStringNotContainsString('class="suggestion"', $body);
    }

    // -----------------------------------------------------------
    // HTML structure
    // -----------------------------------------------------------

    public function testRenderProducesValidHtmlDocument(): void
    {
        // Arrange
        $renderer = new HtmlExceptionResponseRenderer();

        // Act
        $body = $this->getBody($renderer->render(new \RuntimeException('fail')));

        // Assert
        $this->assertStringContainsString('<!DOCTYPE html>', $body);
        $this->assertStringContainsString('<html lang="en">', $body);
        $this->assertStringContainsString('</html>', $body);
    }

    public function testRenderIncludesPageTitleWithStatusCode(): void
    {
        // Arrange
        $renderer = new HtmlExceptionResponseRenderer();

        // Act
        $body = $this->getBody($renderer->render(new HttpException(StatusCode::NotFound)));

        // Assert
        $this->assertStringContainsString('<title>404 Not Found</title>', $body);
    }

    // -----------------------------------------------------------
    // Default descriptions
    // -----------------------------------------------------------

    public function testRenderUsesFriendlyDescriptionWhenNoCustomMessage(): void
    {
        // Arrange
        $renderer = new HtmlExceptionResponseRenderer();

        // Act — no custom message, so it defaults to reason phrase
        $body = $this->getBody($renderer->render(new HttpException(StatusCode::NotFound)));

        // Assert — friendly description instead of just "Not Found"
        $this->assertStringContainsString("doesn&apos;t exist", $body);
        $this->assertStringNotContainsString('>Not Found</p>', $body);
    }

    public function testRenderUsesCustomMessageWhenProvided(): void
    {
        // Arrange
        $renderer = new HtmlExceptionResponseRenderer();

        // Act — custom message provided
        $body = $this->getBody($renderer->render(
            new HttpException(StatusCode::NotFound, 'Order #42 not found'),
        ));

        // Assert — uses the custom message, not the friendly default
        $this->assertStringContainsString('Order #42 not found', $body);
    }

    public function testRenderHasFriendlyDescriptionForCommonCodes(): void
    {
        // Arrange
        $renderer = new HtmlExceptionResponseRenderer();
        $codes = [
            StatusCode::BadRequest,
            StatusCode::Unauthorized,
            StatusCode::Forbidden,
            StatusCode::NotFound,
            StatusCode::MethodNotAllowed,
            StatusCode::UnprocessableEntity,
            StatusCode::TooManyRequests,
            StatusCode::InternalServerError,
            StatusCode::ServiceUnavailable,
        ];

        foreach ($codes as $code) {
            // Act
            $body = $this->getBody($renderer->render(new HttpException($code)));

            // Assert — description differs from the reason phrase
            $this->assertStringNotContainsString(
                '>' . $code->reason()->value . '</p>',
                $body,
                "Status {$code->value} should have a friendly description",
            );
        }
    }

    // -----------------------------------------------------------
    // App override templates
    // -----------------------------------------------------------

    public function testRenderUsesAppTemplateWhenAvailable(): void
    {
        // Arrange
        $fixtureDir = dirname(__DIR__) . '/Fixture/ErrorTemplates';
        $engine = new TemplateEngine(
            compiler: new TemplateCompiler(),
            cache: new TemplateCache(''),
        );
        $resolver = new TemplateResolver(
            rootDirectory: '',
            rootNamespace: 'App',
            errorTemplatesDirectory: $fixtureDir,
        );
        $renderer = new HtmlExceptionResponseRenderer(
            engine: $engine,
            templateResolver: $resolver,
        );

        // Act — 404 has an app template
        $body = $this->getBody($renderer->render(new HttpException(StatusCode::NotFound)));

        // Assert — uses the custom template, not the built-in
        $this->assertStringContainsString('class="custom-error"', $body);
        $this->assertStringContainsString('404', $body);
        $this->assertStringContainsString('Not Found', $body);
    }

    public function testRenderFallsBackToBuiltInWhenNoAppTemplate(): void
    {
        // Arrange
        $fixtureDir = dirname(__DIR__) . '/Fixture/ErrorTemplates';
        $engine = new TemplateEngine(
            compiler: new TemplateCompiler(),
            cache: new TemplateCache(''),
        );
        $resolver = new TemplateResolver(
            rootDirectory: '',
            rootNamespace: 'App',
            errorTemplatesDirectory: $fixtureDir,
        );
        $renderer = new HtmlExceptionResponseRenderer(
            engine: $engine,
            templateResolver: $resolver,
        );

        // Act — 500 has no app template
        $body = $this->getBody($renderer->render(
            new HttpException(StatusCode::InternalServerError),
        ));

        // Assert — uses the built-in renderer
        $this->assertStringContainsString('<!DOCTYPE html>', $body);
        $this->assertStringNotContainsString('custom-error', $body);
    }

    public function testRenderFallsBackWhenNoDirectoryConfigured(): void
    {
        // Arrange — no error templates directory
        $renderer = new HtmlExceptionResponseRenderer();

        // Act
        $body = $this->getBody($renderer->render(new HttpException(StatusCode::NotFound)));

        // Assert — uses the built-in renderer
        $this->assertStringContainsString('<!DOCTYPE html>', $body);
        $this->assertStringNotContainsString('custom-error', $body);
    }

    // -----------------------------------------------------------
    // TemplateResolver integration (co-located error templates)
    // -----------------------------------------------------------

    public function testRenderUsesCoLocatedErrorTemplateViaResolver(): void
    {
        // Arrange — co-located Products.404.html exists
        $rootDir = sys_get_temp_dir() . '/arcanum_exc_resolver_test_' . uniqid();
        mkdir($rootDir . '/app/Domain/Query', 0755, true);
        file_put_contents(
            $rootDir . '/app/Domain/Query/Products.404.html',
            '<div class="co-located">{{ $code }} {{ $title }}</div>',
        );

        $engine = new TemplateEngine(
            compiler: new TemplateCompiler(),
            cache: new TemplateCache(''),
        );
        $resolver = new TemplateResolver($rootDir, 'App');
        $renderer = new HtmlExceptionResponseRenderer(
            engine: $engine,
            templateResolver: $resolver,
        );
        $renderer->setDtoClass('App\\Domain\\Query\\Products');

        // Act
        $body = $this->getBody($renderer->render(new HttpException(StatusCode::NotFound)));

        // Assert — co-located template is used
        $this->assertStringContainsString('class="co-located"', $body);
        $this->assertStringContainsString('404', $body);
        $this->assertStringContainsString('Not Found', $body);

        // Cleanup
        unlink($rootDir . '/app/Domain/Query/Products.404.html');
        rmdir($rootDir . '/app/Domain/Query');
        rmdir($rootDir . '/app/Domain');
        rmdir($rootDir . '/app');
        rmdir($rootDir);
    }

    public function testRenderFallsBackToBuiltInWhenResolverFindsNothing(): void
    {
        // Arrange — resolver configured but no co-located template
        $rootDir = sys_get_temp_dir() . '/arcanum_exc_resolver_test_' . uniqid();
        mkdir($rootDir . '/app', 0755, true);

        $engine = new TemplateEngine(
            compiler: new TemplateCompiler(),
            cache: new TemplateCache(''),
        );
        $resolver = new TemplateResolver($rootDir, 'App');
        $renderer = new HtmlExceptionResponseRenderer(
            engine: $engine,
            templateResolver: $resolver,
        );
        $renderer->setDtoClass('App\\Domain\\Query\\Products');

        // Act
        $body = $this->getBody($renderer->render(new HttpException(StatusCode::NotFound)));

        // Assert — built-in error page
        $this->assertStringContainsString('<!DOCTYPE html>', $body);
        $this->assertStringNotContainsString('co-located', $body);

        // Cleanup
        rmdir($rootDir . '/app');
        rmdir($rootDir);
    }

    public function testRenderPassesValidationErrorsToTemplate(): void
    {
        // Arrange — co-located 422 template that renders $errors
        $rootDir = sys_get_temp_dir() . '/arcanum_exc_resolver_test_' . uniqid();
        mkdir($rootDir . '/app/Domain/Command', 0755, true);
        file_put_contents(
            $rootDir . '/app/Domain/Command/AddEntry.422.html',
            '{{ foreach $errors as $field => $messages }}'
                . '<span>{{ $field }}</span>'
                . '{{ endforeach }}',
        );

        $engine = new TemplateEngine(
            compiler: new TemplateCompiler(),
            cache: new TemplateCache(''),
        );
        $resolver = new TemplateResolver($rootDir, 'App');
        $renderer = new HtmlExceptionResponseRenderer(
            engine: $engine,
            templateResolver: $resolver,
        );
        $renderer->setDtoClass('App\\Domain\\Command\\AddEntry');

        $exception = new ValidationException([
            new ValidationError('name', 'Name is required'),
        ]);

        // Act
        $body = $this->getBody($renderer->render($exception));

        // Assert — template rendered with $errors
        $this->assertStringContainsString('<span>name</span>', $body);

        // Cleanup
        unlink($rootDir . '/app/Domain/Command/AddEntry.422.html');
        rmdir($rootDir . '/app/Domain/Command');
        rmdir($rootDir . '/app/Domain');
        rmdir($rootDir . '/app');
        rmdir($rootDir);
    }

    // -----------------------------------------------------------
    // htmx fragment fallback
    // -----------------------------------------------------------

    public function testRenderReturnsValidationFragmentForHtmxRequest(): void
    {
        // Arrange — no error template, htmx request
        $renderer = new HtmlExceptionResponseRenderer();
        $renderer->setIsHtmxRequest(true);

        $exception = new ValidationException([
            new ValidationError('name', 'Name is required'),
            new ValidationError('email', 'Email is required'),
        ]);

        // Act
        $body = $this->getBody($renderer->render($exception));

        // Assert — minimal <ul> fragment, not full page
        $this->assertStringContainsString('<ul>', $body);
        $this->assertStringContainsString('<li>name: Name is required</li>', $body);
        $this->assertStringContainsString('<li>email: Email is required</li>', $body);
        $this->assertStringNotContainsString('<!DOCTYPE html>', $body);
    }

    public function testRenderReturnsGenericFragmentForHtmxNonValidationError(): void
    {
        // Arrange — no error template, htmx request, 404 error
        $renderer = new HtmlExceptionResponseRenderer();
        $renderer->setIsHtmxRequest(true);

        // Act
        $body = $this->getBody($renderer->render(new HttpException(StatusCode::NotFound)));

        // Assert — minimal <p> fragment, not full page
        $this->assertStringContainsString('<p>', $body);
        $this->assertStringNotContainsString('<!DOCTYPE html>', $body);
    }

    public function testRenderReturnsFullPageForNonHtmxRequest(): void
    {
        // Arrange — no error template, NOT htmx
        $renderer = new HtmlExceptionResponseRenderer();

        // Act
        $body = $this->getBody($renderer->render(new HttpException(StatusCode::NotFound)));

        // Assert — full styled page
        $this->assertStringContainsString('<!DOCTYPE html>', $body);
    }

    public function testRenderPrefersAppTemplateOverHtmxFragment(): void
    {
        // Arrange — app template exists AND htmx request
        $fixtureDir = dirname(__DIR__) . '/Fixture/ErrorTemplates';
        $engine = new TemplateEngine(
            compiler: new TemplateCompiler(),
            cache: new TemplateCache(''),
        );
        $resolver = new TemplateResolver(
            rootDirectory: '',
            rootNamespace: 'App',
            errorTemplatesDirectory: $fixtureDir,
        );
        $renderer = new HtmlExceptionResponseRenderer(
            engine: $engine,
            templateResolver: $resolver,
        );
        $renderer->setIsHtmxRequest(true);

        // Act — 404 has an app template
        $body = $this->getBody($renderer->render(new HttpException(StatusCode::NotFound)));

        // Assert — app template wins over htmx fragment
        $this->assertStringContainsString('class="custom-error"', $body);
    }
}
