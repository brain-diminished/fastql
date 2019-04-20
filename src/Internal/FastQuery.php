<?php

namespace FastQL\Internal;

use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use FastQL\Utils\MutableString;
use PHPUnit\Util\Type;

class FastQuery
{
    /**
     * @var FastEnvironment
     */
    private $environment;
    /**
     * @var Schema
     */
    private $schema;
    /**
     * @var FastTree
     */
    private $joinTree;

    public $select = [];
    public $from = [];
    public $where = [];
    public $order = [];
    public $group = [];

    public function __construct(FastEnvironment $environment, Schema $schema)
    {
        $this->environment = $environment;
        $this->schema = $schema;
        $this->joinTree = new FastTree();
    }

    public function declare(?Table $type, string $name): FastTree
    {
        $object = new FastObject($type, $name);
        $node = new FastTree($object);
        $this->joinTree->add($name ? $name : ($type ? $type->getName() : '_unknown_' . substr(md5(rand()), 0, 8)), $node);
        return $node;
    }

    public function resolve(array $path, ?string $name = null): FastObject
    {
        $key = array_shift($path);
        $root = $this->joinTree->get([$key]) ?? $this->joinTree->find($key);
        if ($root === null) {
            $type = $this->environment->getType($key);
            if (!$this->joinTree->empty()) {
                throw new \Exception("unknown $key ; won't import whole table without join clause unless explicitly told to");
            }
            $root = $this->declare($type, $key);
            $this->import($root->object);
        }
        if (empty($path)) {
            return $root->object;
        }
        $solved = $this->solvePath($root->object, $path);
        $node = $root->deepestPath($solved);
        while ($solved !== null) {
            $property = $solved->getProperty();
            $fk = $solved->getFk();
            $object = $solved->getObject();
            $parent = $node;
            $node = new FastTree($object);
            $parent->add($property, $node);
            $this->join($object, $parent->object, $fk, $property->local(), $property->nullable());
            $solved = $solved->next();
        }
        if ($name !== null && $node->object->anonymous()) {
            $node->object->setIdentifier($name);
        }
        return $node->object;
    }

    public function solvePath(FastObject $start, array $path): FastPath
    {
        $solved = new FastPath($this->environment, $start);
        $nullable = $start->nullable();
        foreach ($path as $str) {
            $property = new FastProperty($str);
            if ($nullable) {
                $property->setNullable(true);
            } else {
                $nullable = $property->nullable();
            }
            if (!$solved->expand($property)) {
                throw new \Exception("cannot solve $str in " . implode('.', $path));
            }
        }
        if ($solved->isAmbiguous()) {
            throw new \Exception('cannot solve ambiguous path ' . implode('.', $path));
        }
        return $solved->next();
    }

    private function join(FastObject $object, FastObject $parent, ForeignKeyConstraint $fk, bool $local = true, bool $nullable = false)
    {
        if ($local) {
            $localColumns = $fk->getLocalColumns();
            $foreignColumns = $fk->getForeignColumns();
        } else {
            $foreignColumns = $fk->getLocalColumns();
            $localColumns = $fk->getForeignColumns();
        }
        $refClause = [];
        foreach ($localColumns as $i => $localColumn) {
            $foreignColumn = $foreignColumns[$i];
            if ($i > 0) {
                $refClause[] = [
                    'expr_type' => 'operator',
                    'base_expr' => 'AND'
                ];
            }
            $refClause[] = [
                'expr_type' => 'colref',
                'base_expr' => new MutableString($parent, ".$localColumn")
            ];
            $refClause[] = [
                'expr_type' => 'operator',
                'base_expr' => '='
            ];
            $refClause[] = [
                'expr_type' => 'colref',
                'base_expr' => new MutableString($object, ".$foreignColumn")
            ];
        }
        $this->from[] = [
            'expr_type' => 'table',
            'table' => $local ? $fk->getForeignTableName() : $fk->getLocalTableName(),
            'alias' => [
                'as' => true,
                'name' => $object
            ],
            'join_type' => $nullable ? 'LEFT' : 'JOIN',
            'ref_type' => 'ON',
            'ref_clause' => $refClause
        ];
    }

    private function import(FastObject $object)
    {
        $this->from[] = [
            'expr_type' => 'table',
            'table' => $object->type()->getName(),
            'alias' => false,
            'join_type' => 'JOIN',
            'ref_type' => false,
        ];
    }

    public function toArray(): array
    {
        $array = [];
        if ($this->select) $array['SELECT'] = $this->select;
        if ($this->from) $array['FROM'] = $this->from;
        if ($this->where) $array['WHERE'] = $this->where;
        if ($this->order) $array['ORDER'] = $this->order;
        if ($this->group) $array['GROUP'] = $this->group;
        return $array;
    }
}
