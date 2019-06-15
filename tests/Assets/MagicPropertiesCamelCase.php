<?php
namespace Affinity4\Magic\Tests\Assets;

use Affinity4\Magic\Magic;

/**
 * Magic Properties Camel Case
 * 
 * Class for testing only
 * 
 * @author Luke Watts <luke@affinity4.ie>
 * 
 * @since 0.0.3
 * 
 * @property string $someProp
 */
class MagicPropertiesCamelCase
{
    use Magic;
    
    private $someProp = 'Some value';

    public function setSomeProp(string $some_prop)
    {
        $this->someProp = 'Some other value';
    }

    public function getSomeProp(): string
    {
        return $this->someProp;
    }
}
