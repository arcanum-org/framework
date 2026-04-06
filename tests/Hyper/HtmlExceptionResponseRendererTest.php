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
}
