<?php
namespace Affinity4\Magic\Tests\Assets;

use Affinity4\Magic\Magic;

class MagicEvent
{
    use Magic;

    public $onSave = [];

    public function save(string $save)
    {
        $this->onSave($save);
    }
}
