<?php

declare(strict_types=1);

namespace Arcanum\Hyper;

use Arcanum\Glitch\ExceptionRenderer;
use Arcanum\Validation\ValidationException;
use Psr\Http\Message\ResponseInterface;

/**
 * Renders ValidationException as a 422 Unprocessable Entity JSON response.
 *
 * Decorates an inner ExceptionRenderer — catches ValidationException and
 * produces structured error output; delegates all other exceptions to the
 * inner renderer.
 *
 * Response body format:
 * ```json
 * {
 *   "errors": {
 *     "name": ["The name field is required."],
 *     "email": ["The email field must be a valid email address."]
 *   }
 * }
 * ```
 */
final class ValidationExceptionRenderer implements ExceptionRenderer
{
    public function __construct(
        private readonly ExceptionRenderer $inner,
        private readonly JsonResponseRenderer $jsonRenderer,
    ) {
    }

    public function render(\Throwable $e): ResponseInterface
    {
        if ($e instanceof ValidationException) {
            return $this->renderValidation($e);
        }

        return $this->inner->render($e);
    }

    private function renderValidation(ValidationException $e): ResponseInterface
    {
        return $this->jsonRenderer->render(
            ['errors' => $e->errorsByField()],
            status: StatusCode::UnprocessableEntity,
        );
    }
}
