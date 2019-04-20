<?php

namespace FastQL\Internal;

use Doctrine\DBAL\Schema\ForeignKeyConstraint;

class FastPath implements \IteratorAggregate
{
    /**
     * @var FastEnvironment
     */
    private $environment;
    /**
     * @var FastObject
     */
    private $object;
    /**
     * @var ForeignKeyConstraint
     */
    private $fk;
    /**
     * @var FastProperty
     */
    private $property;
    /**
     * @var FastPath[]
     */
    private $subs = [];

    public function __construct(FastEnvironment $environment, FastObject $object, ?ForeignKeyConstraint $fk = null, ?FastProperty $property = null)
    {
        $this->environment = $environment;
        $this->object = $object;
        $this->fk = $fk;
        $this->property = $property;
    }

    public function getObject(): FastObject
    {
        return $this->object;
    }

    public function getFk(): ForeignKeyConstraint
    {
        return $this->fk;
    }

    public function getProperty(): FastProperty
    {
        if ($this->property->local() || $this->fk === null) {
            return $this->property;
        } else if ($this->fk) {
            return new FastProperty("$this->property." . $this->fk->getLocalTableName());
        }
    }

    public function isAmbiguous(): bool
    {
        switch (count($this->subs)) {
            case 0:
                return false;
            case 1:
                return current($this->subs)->isAmbiguous();
            default:
                return true;
        }
    }

    public function expand(FastProperty $property): bool
    {
        if (!empty($this->subs)) {
            return $this->expandRecursive($property);
        } else if ($property->local()) {
            return $this->expandLocal($property);
        } else {
            return $this->expandForeign($property);
        }
    }

    private function expandLocal(FastProperty $property): bool
    {
        if ($this->fk && !$this->property->local() && $property->name() === $this->fk->getLocalTableName()) {
            // Solve ambiguity explicitly
            return true;
        } else if ($this->environment->hasProperty($this->object->type(), $property->name())) {
            $fk = $this->environment->getProperty($this->object->type(), $property->name());
            $subType = $this->environment->getType($fk->getForeignTableName());
            $subObject = new FastObject($subType, null, $property->nullable());
            $this->subs[] = new FastPath($this->environment, $subObject, $fk, $property);
            return true;
        } else {
            return false;
        }
    }

    private function expandForeign(FastProperty $property): bool
    {
        foreach ($this->environment->findProperties($property->name()) as $fk) {
            if ($fk->getForeignTableName() === $this->object->type()->getName()) {
                $subType = $this->environment->getType($fk->getLocalTableName());
                $subObject = new FastObject($subType, null, $property->nullable());
                $this->subs[] = new FastPath($this->environment, $subObject, $fk, $property);
            }
        }
        return !empty($this->subs);
    }

    private function expandRecursive(FastProperty $property): bool
    {
        foreach ($this->subs as $i => $sub) {
            if (!$sub->expand($property)) {
                unset($this->subs[$i]);
            }
        }
        return !empty($this->subs);
    }

    public function next(): ?FastPath
    {
        return !empty($this->subs) ? current($this->subs) : null;
    }

    public function size(): int
    {
        return empty($this->subs) ? 1 : 1 + $this->next()->size();
    }

    /**
     * @return iterable|FastPath[]
     */
    public function getIterator(): iterable
    {
        for ($it = $this; $it = $it->next();) {
            yield $it;
        }
    }
}
