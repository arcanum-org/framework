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
use Arcanum\Hyper\JsonExceptionResponseRenderer;
use Arcanum\Hyper\JsonResponseRenderer;
use Arcanum\Hyper\Message;
use Arcanum\Hyper\Phrase;
use Arcanum\Hyper\Response;
use Arcanum\Hyper\StatusCode;
use Arcanum\Hyper\Version;
use Arcanum\Shodo\Formatters\JsonFormatter;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Psr\Http\Message\ResponseInterface;

#[CoversClass(JsonExceptionResponseRenderer::class)]
#[UsesClass(JsonResponseRenderer::class)]
#[UsesClass(JsonFormatter::class)]
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
final class JsonExceptionResponseRendererTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private function decodeErrorPayload(ResponseInterface $response): array
    {
        $body = $response->getBody();
        $body->rewind();
        $decoded = json_decode($body->getContents(), true, 512, \JSON_THROW_ON_ERROR);
        assert(is_array($decoded) && isset($decoded['error']) && is_array($decoded['error']));
        /** @var array<string, mixed> */
        return $decoded['error'];
    }

    // -----------------------------------------------------------
    // Response structure
    // -----------------------------------------------------------

    public function testRenderReturnsResponseInterface(): void
    {
        // Arrange
        $renderer = new JsonExceptionResponseRenderer(new JsonResponseRenderer());

        // Act
        $response = $renderer->render(new \RuntimeException('Something broke'));

        // Assert
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testRenderSetsJsonContentType(): void
    {
        // Arrange
        $renderer = new JsonExceptionResponseRenderer(new JsonResponseRenderer());

        // Act
        $response = $renderer->render(new \RuntimeException('fail'));

        // Assert
        $this->assertSame('application/json', $response->getHeaderLine('Content-Type'));
    }

    // -----------------------------------------------------------
    // Status code mapping
    // -----------------------------------------------------------

    public function testRenderUsesStatusCodeFromHttpException(): void
    {
        // Arrange
        $renderer = new JsonExceptionResponseRenderer(new JsonResponseRenderer());

        // Act
        $response = $renderer->render(new HttpException(StatusCode::NotFound, 'Order not found'));

        // Assert
        $this->assertSame(404, $response->getStatusCode());
    }

    public function testRenderUsesStatusCodeFromHttpExceptionVariants(): void
    {
        // Arrange
        $renderer = new JsonExceptionResponseRenderer(new JsonResponseRenderer());

        // Assert
        $this->assertSame(403, $renderer->render(new HttpException(StatusCode::Forbidden))->getStatusCode());
        $this->assertSame(422, $renderer->render(new HttpException(StatusCode::UnprocessableEntity))->getStatusCode());
        $this->assertSame(503, $renderer->render(new HttpException(StatusCode::ServiceUnavailable))->getStatusCode());
    }

    public function testRenderDefaultsToInternalServerErrorForGenericExceptions(): void
    {
        // Arrange
        $renderer = new JsonExceptionResponseRenderer(new JsonResponseRenderer());

        // Act
        $response = $renderer->render(new \RuntimeException('unexpected'));

        // Assert
        $this->assertSame(500, $response->getStatusCode());
    }

    // -----------------------------------------------------------
    // JSON payload — production mode
    // -----------------------------------------------------------

    public function testRenderProductionIncludesStatusAndMessage(): void
    {
        // Arrange
        $renderer = new JsonExceptionResponseRenderer(new JsonResponseRenderer(), debug: false);

        // Act
        $response = $renderer->render(new HttpException(StatusCode::NotFound, 'Order not found'));
        $error = $this->decodeErrorPayload($response);

        // Assert
        $this->assertSame(404, $error['status']);
        $this->assertSame('Order not found', $error['message']);
    }

    public function testRenderProductionExcludesDebugInfo(): void
    {
        // Arrange
        $renderer = new JsonExceptionResponseRenderer(new JsonResponseRenderer(), debug: false);

        // Act
        $response = $renderer->render(new \RuntimeException('fail'));
        $error = $this->decodeErrorPayload($response);

        // Assert
        $this->assertArrayNotHasKey('exception', $error);
        $this->assertArrayNotHasKey('file', $error);
        $this->assertArrayNotHasKey('line', $error);
        $this->assertArrayNotHasKey('trace', $error);
    }

    // -----------------------------------------------------------
    // JSON payload — debug mode
    // -----------------------------------------------------------

    public function testRenderDebugIncludesExceptionClass(): void
    {
        // Arrange
        $renderer = new JsonExceptionResponseRenderer(new JsonResponseRenderer(), debug: true);

        // Act
        $response = $renderer->render(new \RuntimeException('fail'));
        $error = $this->decodeErrorPayload($response);

        // Assert
        $this->assertSame('RuntimeException', $error['exception']);
    }

    public function testRenderDebugIncludesFileAndLine(): void
    {
        // Arrange
        $renderer = new JsonExceptionResponseRenderer(new JsonResponseRenderer(), debug: true);

        // Act
        $response = $renderer->render(new \RuntimeException('fail'));
        $error = $this->decodeErrorPayload($response);

        // Assert
        $this->assertArrayHasKey('file', $error);
        $this->assertArrayHasKey('line', $error);
        $this->assertSame(__FILE__, $error['file']);
        $this->assertIsInt($error['line']);
    }

    public function testRenderDebugIncludesTrace(): void
    {
        // Arrange
        $renderer = new JsonExceptionResponseRenderer(new JsonResponseRenderer(), debug: true);

        // Act
        $response = $renderer->render(new \RuntimeException('fail'));
        $error = $this->decodeErrorPayload($response);

        // Assert
        $this->assertArrayHasKey('trace', $error);
        $this->assertIsArray($error['trace']);
    }

    // -----------------------------------------------------------
    // Default debug mode
    // -----------------------------------------------------------

    public function testDebugDefaultsToFalse(): void
    {
        // Arrange
        $renderer = new JsonExceptionResponseRenderer(new JsonResponseRenderer());

        // Act
        $response = $renderer->render(new \RuntimeException('fail'));
        $error = $this->decodeErrorPayload($response);

        // Assert
        $this->assertArrayNotHasKey('trace', $error);
    }

    // -----------------------------------------------------------
    // ArcanumException — title
    // -----------------------------------------------------------

    public function testRenderIncludesTitleForArcanumExceptions(): void
    {
        // Arrange
        $renderer = new JsonExceptionResponseRenderer(new JsonResponseRenderer());

        // Act
        $response = $renderer->render(new HttpException(StatusCode::NotFound, 'Order #42 not found'));
        $error = $this->decodeErrorPayload($response);

        // Assert
        $this->assertSame('Not Found', $error['title']);
    }

    public function testRenderExcludesTitleForGenericExceptions(): void
    {
        // Arrange
        $renderer = new JsonExceptionResponseRenderer(new JsonResponseRenderer());

        // Act
        $response = $renderer->render(new \RuntimeException('fail'));
        $error = $this->decodeErrorPayload($response);

        // Assert
        $this->assertArrayNotHasKey('title', $error);
    }

    // -----------------------------------------------------------
    // ArcanumException — suggestion
    // -----------------------------------------------------------

    public function testRenderIncludesSuggestionWhenVerboseErrorsEnabled(): void
    {
        // Arrange
        $renderer = new JsonExceptionResponseRenderer(
            new JsonResponseRenderer(),
            verboseErrors: true,
        );
        $exception = (new HttpException(StatusCode::NotFound, 'Order not found'))
            ->withSuggestion('Check the order ID and try again');

        // Act
        $response = $renderer->render($exception);
        $error = $this->decodeErrorPayload($response);

        // Assert
        $this->assertSame('Check the order ID and try again', $error['suggestion']);
    }

    public function testRenderExcludesSuggestionWhenVerboseErrorsDisabled(): void
    {
        // Arrange
        $renderer = new JsonExceptionResponseRenderer(
            new JsonResponseRenderer(),
            verboseErrors: false,
        );
        $exception = (new HttpException(StatusCode::NotFound, 'Order not found'))
            ->withSuggestion('Check the order ID and try again');

        // Act
        $response = $renderer->render($exception);
        $error = $this->decodeErrorPayload($response);

        // Assert
        $this->assertArrayNotHasKey('suggestion', $error);
    }

    public function testRenderExcludesSuggestionWhenNullEvenIfVerbose(): void
    {
        // Arrange
        $renderer = new JsonExceptionResponseRenderer(
            new JsonResponseRenderer(),
            verboseErrors: true,
        );

        // Act — HttpException with no suggestion set
        $response = $renderer->render(new HttpException(StatusCode::NotFound));
        $error = $this->decodeErrorPayload($response);

        // Assert
        $this->assertArrayNotHasKey('suggestion', $error);
    }

    public function testVerboseErrorsDefaultsToFalse(): void
    {
        // Arrange
        $renderer = new JsonExceptionResponseRenderer(new JsonResponseRenderer());
        $exception = (new HttpException(StatusCode::NotFound))
            ->withSuggestion('Try something else');

        // Act
        $response = $renderer->render($exception);
        $error = $this->decodeErrorPayload($response);

        // Assert
        $this->assertArrayNotHasKey('suggestion', $error);
    }
}
