<?php
namespace Affinity4\Magic\Tests\Assets;

class InvokableEventHandler
{
    private $number = 0;

    public function __construct(int $number)
    {
        $this->number = $number;
    }

    public function __invoke(string $save)
    {
        echo "$save {$this->number}\n";
    }
}
