<?php
namespace Affinity4\Magic\Tests\Assert;

use Affinity4\Magic\Tests\Assert\Constraint\PropertyExists;

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
}
