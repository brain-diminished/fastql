<?php

namespace FastQL\Internal;

class FastProperty
{
    /**
     * @var bool
     */
    private $local;
    /**
     * @var bool
     */
    private $nullable;
    /**
     * @var string
     */
    private $name;

    public function __construct(string $property)
    {
        preg_match('(^(\\\\?)(\??)(.*))', $property, $matches);
        $this->local = empty($matches[1]);
        $this->nullable = !empty($matches[2]);
        $this->name = $matches[3];
        if (count(explode('|', $this->name)) > 1) {
            throw new \Exception('disjunction feature is not implemented yet');
        }
        if (count(explode('&', $this->name)) > 1) {
            throw new \Exception('conjunction feature is not implemented yet');
        }
    }

    public function name()
    {
        return $this->name;
    }

    public function setName(string $name)
    {
        $this->name = $name;
    }

    public function local()
    {
        return $this->local;
    }

    public function setLocal(bool $local)
    {
        $this->local = $local;
    }

    public function nullable()
    {
        return $this->nullable;
    }

    public function setNullable(bool $nullable)
    {
        $this->nullable = $nullable;
    }

    public function __toString()
    {
        return ($this->local ? '' : '\\') . ($this->nullable ? '?' : '') . $this->name;
    }
}
