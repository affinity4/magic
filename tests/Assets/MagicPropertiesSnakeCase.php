<?php
namespace Affinity4\Magic\Tests\Assets;

use Affinity4\Magic\Magic;

/**
 * Magic Properties Snake Case
 * 
 * Class for testing only
 * 
 * @author Luke Watts <luke@affinity4.ie>
 * 
 * @since 0.0.3
 * 
 * @property string $some_prop
 */
class MagicPropertiesSnakeCase
{
    use Magic;
    
    private $some_prop = 'Some value';

    public function setSomeProp(string $some_prop)
    {
        $this->some_prop = 'Some other value';
    }

    public function getSomeProp(): string
    {
        return $this->some_prop;
    }
}
