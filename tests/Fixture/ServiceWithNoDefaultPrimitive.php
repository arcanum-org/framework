<?php

declare(strict_types=1);

namespace Arcanum\Test\Fixture;

class ServiceWithNoDefaultPrimitive
{
    public function __construct(
        private DependencyWithNoDefaultPrimitive $dependency,
    ) {
    }

    public function getDependency(): DependencyWithNoDefaultPrimitive
    {
        return $this->dependency;
    }
}
