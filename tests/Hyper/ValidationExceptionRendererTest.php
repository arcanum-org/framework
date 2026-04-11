<?php

declare(strict_types=1);

namespace Arcanum\Test\Hyper;

use Arcanum\Flow\River\LazyResource;
use Arcanum\Flow\River\Stream;
use Arcanum\Flow\River\StreamResource;
use Arcanum\Gather\IgnoreCaseRegistry;
use Arcanum\Gather\Registry;
use Arcanum\Hyper\Headers;
use Arcanum\Hyper\JsonExceptionResponseRenderer;
use Arcanum\Hyper\JsonResponseRenderer;
use Arcanum\Hyper\Message;
use Arcanum\Hyper\Phrase;
use Arcanum\Hyper\Response;
use Arcanum\Hyper\StatusCode;
use Arcanum\Hyper\ValidationExceptionRenderer;
use Arcanum\Hyper\Version;
use Arcanum\Shodo\Formatters\JsonFormatter;
use Arcanum\Validation\ValidationError;
use Arcanum\Validation\ValidationException;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

#[CoversClass(ValidationExceptionRenderer::class)]
#[UsesClass(JsonResponseRenderer::class)]
#[UsesClass(JsonExceptionResponseRenderer::class)]
#[UsesClass(JsonFormatter::class)]
#[UsesClass(ValidationException::class)]
#[UsesClass(ValidationError::class)]
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
final class ValidationExceptionRendererTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private function decodePayload(\Psr\Http\Message\ResponseInterface $response): array
    {
        $body = $response->getBody();
        $body->rewind();
        $decoded = json_decode($body->getContents(), true, 512, \JSON_THROW_ON_ERROR);
        assert(is_array($decoded));
        /** @var array<string, mixed> */
        return $decoded;
    }

    private function makeRenderer(): ValidationExceptionRenderer
    {
        $jsonRenderer = new JsonResponseRenderer();
        $inner = new JsonExceptionResponseRenderer($jsonRenderer);
        return new ValidationExceptionRenderer($inner, $jsonRenderer);
    }

    public function testReturns422ForValidationException(): void
    {
        $renderer = $this->makeRenderer();

        $exception = new ValidationException([
            new ValidationError('name', 'The name field is required.'),
        ]);

        $response = $renderer->render($exception);

        $this->assertSame(422, $response->getStatusCode());
    }

    public function testBodyContainsFieldKeyedErrors(): void
    {
        $renderer = $this->makeRenderer();

        $exception = new ValidationException([
            new ValidationError('name', 'The name field is required.'),
            new ValidationError('email', 'The email field must be a valid email address.'),
        ]);

        $response = $renderer->render($exception);
        $payload = $this->decodePayload($response);

        $this->assertArrayHasKey('errors', $payload);
        $this->assertIsArray($payload['errors']);
        $this->assertSame(['The name field is required.'], $payload['errors']['name']);
        $this->assertSame(
            ['The email field must be a valid email address.'],
            $payload['errors']['email'],
        );
    }

    public function testHandlesMultipleErrorsOnSameField(): void
    {
        $renderer = $this->makeRenderer();

        $exception = new ValidationException([
            new ValidationError('name', 'The name field is required.'),
            new ValidationError('name', 'The name field must be at least 3 characters.'),
        ]);

        $response = $renderer->render($exception);
        $payload = $this->decodePayload($response);

        $this->assertIsArray($payload['errors']);
        $errors = $payload['errors'];
        $this->assertIsArray($errors['name']);
        $this->assertCount(2, $errors['name']);
    }

    public function testDelegatesNonValidationExceptionsToInner(): void
    {
        $renderer = $this->makeRenderer();

        $response = $renderer->render(new \RuntimeException('Something broke'));

        $this->assertSame(500, $response->getStatusCode());
    }

    public function testSetsJsonContentType(): void
    {
        $renderer = $this->makeRenderer();

        $exception = new ValidationException([
            new ValidationError('name', 'fail'),
        ]);

        $response = $renderer->render($exception);

        $this->assertSame('application/json', $response->getHeaderLine('Content-Type'));
    }
}
