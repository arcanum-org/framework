<?php

declare(strict_types=1);

namespace Arcanum\Test\Fixture;

class ParentClass extends SimpleDependency
{
    public SimpleDependency $dependency;
    public function __construct(parent $dependency)
    {
        $this->dependency = $dependency;
    }
}
