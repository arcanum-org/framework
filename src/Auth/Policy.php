<?php

declare(strict_types=1);

namespace Arcanum\Auth;

/**
 * Authorization logic that depends on the DTO's data.
 *
 * Policies handle cases where role-based authorization isn't enough —
 * e.g., "users can only edit their own posts." The policy receives
 * the authenticated Identity and the DTO, and returns whether the
 * action is allowed.
 *
 * Policies are resolved from the container so they can access
 * repositories, services, or configuration.
 *
 * ```php
 * final class OwnsPostPolicy implements Policy
 * {
 *     public function __construct(private PostRepository $posts) {}
 *
 *     public function authorize(Identity $identity, object $dto): bool
 *     {
 *         $post = $this->posts->find($dto->postId);
 *         return $post->authorId === $identity->id();
 *     }
 * }
 * ```
 */
interface Policy
{
    public function authorize(Identity $identity, object $dto): bool;
}
