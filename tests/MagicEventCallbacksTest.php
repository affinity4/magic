<?php
namespace Affinity4\Magic\Tests;

use PHPUnit\Framework\TestCase;
use Affinity4\Magic\Tests\Assert\Assertions;
use Affinity4\Magic\Tests\Assets\MagicEvent;
use Affinity4\Magic\Magic;

/**
 * @group MagicEventTest
 * @group MagicEventCallbacksTest
 */
class MagicEventCallbacksTest extends TestCase
{
    use Assertions;

    public function testClassHasMagicTrait()
    {
        $ReflectionClass = new \ReflectionClass(MagicEvent::class);
        $traits = $ReflectionClass->getTraits();

        $this->assertArrayHasKey(Magic::class, $traits);
    }

    public function testOnSavePropertyExists()
    {
        $this->assertPropertyExists(MagicEvent::class, 'onSave');
    }

    public function testAddingEventHandlersAndTriggeringEvent()
    {
        $MagicEvent = new MagicEvent;
        $MagicEvent->onSave[] = function($save) {
            echo "$save 1\n";
        };
        $MagicEvent->onSave[] = function($save) {
            echo "$save 2\n";
        };
        $MagicEvent->onSave[] = function($save) {
            echo "$save 3\n";
        };

        ob_start();
        $MagicEvent->onSave('Save');
        $output = ob_get_clean();

        $this->assertEquals("Save 1\nSave 2\nSave 3\n", $output);
    }


}
