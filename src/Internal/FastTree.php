<?php

namespace FastQL\Internal;

class FastTree
{
    /**
     * @var FastObject
     */
    public $object;
    /**
     * @var FastTree[];
     */
    private $children = [];

    public function __construct(?FastObject $object = null)
    {
        $this->object = $object;
    }

    public function add(string $name, FastTree $node)
    {
        $this->children[$name] = $node;
    }

    public function get(array $path = []): ?FastTree
    {
        if (empty($path)) {
            return $this;
        }
        $key = array_shift($path);
        if (key_exists($key, $this->children)) {
            return $this->children[$key]->get($path);
        } else {
            return null;
        }
    }

    public function deepest(array &$path): FastTree
    {
        if (empty($path) || !key_exists(current($path), $this->children)) {
            return $this;
        } else {
            $key = array_shift($path);
            return $this->children[$key]->deepest($path);
        }
    }

    public function deepestPath(?FastPath &$path): FastTree
    {
        if ($path === null) {
            return $this;
        }
        $property = $path->getProperty()->__toString();
        if (!key_exists($property, $this->children)) {
            return $this;
        } else {
            $path = $path->next();
            return $this->children[$property]->deepestPath($path);
        }
    }

    public function find(string $identifier): ?FastTree
    {
        if ($this->object && !$this->object->anonymous() && $this->object->identifier() === $identifier) {
            return $this;
        }
        foreach ($this->children as $child) {
            $found = $child->find($identifier);
            if ($found !== null) {
                return $found;
            }
        }
        return null;
    }

    public function empty(): bool
    {
        return empty($this->children);
    }

    public function __toString(): string
    {
        return $this->stringify(0);
    }

    private function stringify(int $indentLevel): string
    {
        if ($this->object === null) {
            $type = 'NULL';
        } else if ($this->object->type() === null) {
            $type = 'UNKNOWN';
        } else {
            $type = $this->object->type()->getName();
        }
        $indent = str_repeat('  ', $indentLevel);
        $str = "${indent}type: $type" . PHP_EOL;
        if (!empty($this->children)) {
            foreach ($this->children as $key => $child) {
                $str .= "${indent}$key:" . PHP_EOL;
                $str .= $child->stringify($indentLevel + 1);
            }
        }
        return $str;
    }
}
