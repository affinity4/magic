<?php
namespace Affinity4\Magic\Tests\Assert;

use Affinity4\Magic\Tests\Assert\Constraint\PropertyExists;
use Affinity4\Magic\Tests\Assert\Constraint\MethodExists;

trait Assertions
{
    public function assertPropertyExists(string $class, string $property, string $message = '')
    {
        Assert::assertPropertyExists($class, $property);
    }

    public function assertNotPropertyExists(string $class, string $property, string $message = '')
    {
        Assert::assertNotPropertyExists($class, $property);
    }

    public function assertMethodExists(string $class, string $method, string $message = '')
    {
        Assert::assertMethodExists($class, $method);
    }

    public function assertNotMethodExists(string $class, string $method, string $message = '')
    {
        Assert::assertNotMethodExists($class, $method);
    }
}
