<?php
namespace Affinity4\Magic\Tests\Assert;

use Affinity4\Magic\Tests\Assert\Constraint\PropertyExists;
use Affinity4\Magic\Tests\Assert\Constraint\MethodExists;
use PHPUnit\Framework\Assert as PHPUnitAssert;

class Assert
{
    public static function assertPropertyExists(string $class, string $property, string $message = '')
    {
        PHPUnitAssert::assertThat($class, new PropertyExists($property), $message);
    }

    public static function assertNotPropertyExists(string $class, string $property, string $message = '')
    {
        $constraint = PHPUnitAssert::logicalNot(new PropertyExists($property));

        PHPUnitAssert::assertThat($class, $constraint, $message);
    }

    public static function assertMethodExists(string $class, string $method, string $message = '')
    {
        PHPUnitAssert::assertThat($class, new MethodExists($method), $message);
    }

    public static function assertNotMethodExists(string $class, string $method, string $message = '')
    {
        $constraint = PHPUnitAssert::logicalNot(new MethodExists($method));

        PHPUnitAssert::assertThat($class, $constraint, $message);
    }
}