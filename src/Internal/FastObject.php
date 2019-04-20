<?php

namespace FastQL\Internal;

use Doctrine\DBAL\Schema\Table;

class FastObject
{
    /**
     * @var Table
     */
    private $type;
    /**
     * @var string
     */
    private $identifier;
    /**
     * @var bool
     */
    private $anonymous;
    /**
     * @var bool
     */
    private $nullable = true;

    public function __construct(Table $type, ?string $identifier = null, bool $nullable = false)
    {
        $this->type = $type;
        $this->identifier = $identifier ?? '__anon__' . substr(sha1(rand()), 0, 8);
        $this->anonymous = $identifier === null;
        $this->nullable = $nullable;
    }

    public function type(): ?Table
    {
        return $this->type;
    }

    public function identifier(): string
    {
        return $this->identifier;
    }

    public function setIdentifier(string $identifier): void
    {
        $this->identifier = $identifier;
        $this->anonymous = false;
    }

    public function anonymous(): bool
    {
        return $this->anonymous;
    }

    public function nullable(): bool
    {
        return $this->nullable;
    }

    public function setNullable(bool $nullable)
    {
        $this->nullable = $nullable;
    }

    public function __toString()
    {
        return $this->identifier;
    }
}
