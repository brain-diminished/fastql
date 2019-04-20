<?php

namespace FastQL\Utils;

class MutableString
{
    /**
     * @var array
     */
    public $items;

    public function __construct(...$items)
    {
        $this->items = $items;
    }

    public function __toString()
    {
        return implode('', $this->items);
    }
}
