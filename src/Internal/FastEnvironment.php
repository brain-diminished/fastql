<?php

namespace FastQL\Internal;

use Doctrine\Common\Inflector\Inflector;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;

class FastEnvironment
{
    /**
     * @var Schema
     */
    private $schema;
    /**
     * @var ForeignKeyConstraint[][]
     */
    private $propertyDictionary = [];

    public function __construct(Schema $schema)
    {
        $this->schema = $schema;
        $this->initialize();
    }

    public function getType(string $name): Table
    {
        return $this->schema->getTable($name);
    }

    public function hasProperty(Table $table, string $name): bool
    {
        return isset($this->propertyDictionary[$table->getName()][$name]);
    }

    public function getProperty(Table $table, string $name): ForeignKeyConstraint
    {
        if (isset($this->propertyDictionary[$table->getName()][$name])) {
            return $this->propertyDictionary[$table->getName()][$name];
        } else {
            return null;
        }
    }

    /**
     * @param string $property
     * @return ForeignKeyConstraint[]
     */
    public function findProperties(string $property)
    {
        $found = [];
        foreach ($this->propertyDictionary as $table => $properties) {
            if (isset($properties[$property])) {
                $found[] = $properties[$property];
            }
        }
        return $found;
    }

    private function initialize()
    {
        foreach ($this->schema->getTables() as $table) {
            foreach ($table->getForeignKeys() as $foreignKey) {
                $propertyName = $this->getPropertyName($foreignKey);
                $this->declareProperty($table->getName(), $propertyName, $foreignKey);
            }
        }
    }

    private function getPropertyName(ForeignKeyConstraint $fk)
    {
        $tableName = $fk->getLocalTableName();
        if (preg_match("(^fk_${tableName}__(.*)$)", $fk->getName(), $matches)) {
            return $matches[1];
        }
        if (count($fk->getLocalColumns()) === 1) {
            if (preg_match('(^(.*)_id$)', $fk->getLocalColumns()[0], $matches)) {
                return $matches[1];
            }
            if (preg_match('(^id_(.*)$)', $fk->getLocalColumns()[0], $matches)) {
                return $matches[1];
            }
        }
        return Inflector::singularize($fk->getForeignTableName());
    }

    private function declareProperty(string $table, string $name, $descriptor)
    {
        if (!isset($this->propertyDictionary[$table])) $this->propertyDictionary[$table] = [];
        $this->propertyDictionary[$table][$name] = $descriptor;
    }
}
