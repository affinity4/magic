<?php
namespace Affinity4\Magic\Tests;

use PHPUnit\Framework\TestCase;
use Affinity4\Magic\Tests\Assert\Assertions;
use Affinity4\Magic\Tests\Assets\MagicPropertiesSnakeCase;

/**
 * @group MagicProperties
 * @group MagicPropertiesSnakeCaseTest
 */
class MagicPropertiesSnakeCaseTest extends TestCase
{
    use Assertions;

    public function testSomePropExists()
    {
        $this->assertPropertyExists(MagicPropertiesSnakeCase::class, 'some_prop');
        $this->assertNotPropertyExists(MagicPropertiesSnakeCase::class, 'someProp');
    }

    private function getPropertyAttributesFromDocBlock(string $doc_block)
    {
        $doc_block_lines = explode("\n", str_replace("\r\n", "\n", $doc_block));
        $filter_out_everything_but_property_atts = function(array $lines) {
            $filtered = array_filter($lines, function($line) {
                return (trim($line) !== '*' && !empty(trim($line)));
            });

            $filtered = array_map(function($line) {
                $line = trim($line);

                return preg_replace('/^\*(\s)*/', '', $line);
            }, $filtered);

            $filter = array_filter($filtered, function($line) {
                return (trim($line) !== '/**' && trim($line) !== '/');
            });

            return array_filter($filter, function($line) {
                return (preg_match('/^@property.*/', $line) === 1);
            });
        };

        $properties = $filter_out_everything_but_property_atts($doc_block_lines);

        $create_key_value_pair_from_attribute = function(array $properties) {
            $key_values = [];
            foreach ($properties as $property_attribute) {
                $key = preg_replace('/^(@property(-read|write)?)(.*)/', '$1', $property_attribute);
                $value = preg_replace('/^(@property(-read|write)?).*(\$\w+)/', '$3', $property_attribute);
                $key_values[$key][] = $value;
            }

            return $key_values;
        };

        return $create_key_value_pair_from_attribute($properties);
    }

    public function testClassHasPropertyAttributeInDocBlock()
    {
        $ReflectionClass = new \ReflectionClass(MagicPropertiesSnakeCase::class);
        $doc_block = $ReflectionClass->getDocComment();

        $this->assertNotFalse($doc_block);

        $property_atts = $this->getPropertyAttributesFromDocBlock($doc_block);
        $this->assertNotEmpty($property_atts);
        $this->assertArrayHasKey('@property', $property_atts);
    }

    /**
     * @depends testClassHasPropertyAttributeInDocBlock
     */
    public function testClassHasPropertyAttributeSomePropInDocBlock()
    {
        $ReflectionClass = new \ReflectionClass(MagicPropertiesSnakeCase::class);
        $doc_block = $ReflectionClass->getDocComment();

        $this->assertNotFalse($doc_block);

        $property_atts = $this->getPropertyAttributesFromDocBlock($doc_block);
        $properties = $property_atts['@property'];

        $this->assertContains('$some_prop', $properties);
    }

    public function testClassHasMagicTrait()
    {
        $ReflectionClass = new \ReflectionClass(MagicPropertiesSnakeCase::class);
        $traits = $ReflectionClass->getTraits();

        $this->assertArrayHasKey(\Affinity4\Magic\Magic::class, $traits);
    }

    /**
     * @depends testSomePropExists
     * @depends testClassHasMagicTrait
     */
    public function testGettingValueFromMagicPropertyDirectlyIsSuccessful()
    {
        $MagicPropertiesSnakeCase = new MagicPropertiesSnakeCase;

        $this->assertEquals('Some value', $MagicPropertiesSnakeCase->some_prop);
    }

    /**
     * @depends testSomePropExists
     * @depends testClassHasMagicTrait
     * @depends testGettingValueFromMagicPropertyDirectlyIsSuccessful
     */
    public function testSettingValueFromMagicPropertyDirectlyIsSuccessful()
    {
        $MagicPropertiesSnakeCase = new MagicPropertiesSnakeCase;

        $MagicPropertiesSnakeCase->some_prop = 'Some other value';

        $this->assertEquals('Some other value', $MagicPropertiesSnakeCase->some_prop);
    }
}