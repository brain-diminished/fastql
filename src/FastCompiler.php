<?php

namespace FastQL;

use Doctrine\Common\Inflector\Inflector;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use FastQL\Internal\FastEnvironment;
use FastQL\Internal\FastQuery;
use FastQL\Utils\MutableString;
use PHPSQLParser\PHPSQLCreator;
use PHPSQLParser\PHPSQLParser;

class FastCompiler
{
    /**
     * @var FastEnvironment
     */
    private $environment;
    /**
     * @var Schema
     */
    private $schema;

    public function __construct(Schema $schema)
    {
        $this->environment = new FastEnvironment($schema);
        $this->schema = $schema;
    }

    public function compile(string $fastql): string
    {
        $parser = new PHPSQLParser();
        $parsed = $parser->parse($fastql);
        $compiled = $this->compileParsed($parsed);
        $creator = new PHPSQLCreator();
        return $creator->create($compiled);
    }

    private function compileParsed(array $parsed): array
    {
        $query = new FastQuery($this->environment, $this->schema);
        if (isset($parsed['FROM'])) {
            foreach ($parsed['FROM'] as $sub) {
                $processed = $this->process($sub, $query);
                if ($processed)
                    $query->from[] = $processed;
            }
        }
        if (isset($parsed['SELECT'])) {
            foreach ($parsed['SELECT'] as $sub) {
                $processed = $this->process($sub, $query);
                if ($processed)
                    $query->select[] = $processed;
            }
        }
        if (isset($parsed['WHERE'])) {
            foreach ($parsed['WHERE'] as $sub) {
                $processed = $this->process($sub, $query);
                if ($processed)
                    $query->where[] = $processed;
            }
        }
        if (isset($parsed['ORDER'])) {
            foreach ($parsed['ORDER'] as $sub) {
                $processed = $this->process($sub, $query);
                if ($processed)
                    $query->order[] = $processed;
            }
        }
        if (isset($parsed['GROUP'])) {
            foreach ($parsed['GROUP'] as $sub) {
                $processed = $this->process($sub, $query);
                if ($processed)
                    $query->group[] = $processed;
            }
        }
        return $query->toArray();
    }

    private function process(array $parsed, FastQuery $query): ?array
    {
        switch ($parsed['expr_type']) {
            case 'colref':
                return $this->colref($parsed, $query);
            case 'table':
                return $this->table($parsed, $query);
            case 'function':
            case 'expression':
            case 'brackets_expression':
            case 'aggregate_function':
                return $this->subtree($parsed, $query);
            case 'subquery':
                return $this->subquery($parsed, $query);
            default:
                return $parsed;
        }
    }

    private function processMany(array $parsed, FastQuery $query): ?array
    {
        foreach ($parsed as $i => $sub) {
            $parsed[$i] = $this->process($sub, $query);
        }
        return $parsed;
    }

    private function colref(array $parsed, FastQuery $query)
    {
        if (!isset($parsed['no_quotes'])) {
            return $parsed;
        }
        $objectPath = $parsed['no_quotes']['parts'];
        $property = array_pop($objectPath);

        if (!empty($objectPath)) {
            $object = $query->resolve($objectPath);
            $parsed['base_expr'] = new MutableString($object, '.' . $property);
        }
        // TODO: check that $property is actually a column: if not, make scalar using primary key (if possible).
        return $parsed;
    }

    private function table($parsed, FastQuery $query): ?array
    {
        if (count($parsed['no_quotes']['parts']) === 1) {
            $tableName = $parsed['no_quotes']['parts'][0];
            $type = $this->schema->getTable($tableName);
            $name = $parsed['alias'] ? $this->formatNoQuotes($parsed['alias']['no_quotes']) : $tableName;
            $query->declare($type, $name);
            if ($parsed['ref_clause']) {
                $parsed['ref_clause'] = $this->processMany($parsed['ref_clause'], $query);
            }
            return $parsed;
        } else {
            return $this->autoJoin($parsed, $query);
        }
    }

    private function autoJoin($parsed, FastQuery $query): ?array
    {
        $objectPath = $parsed['no_quotes']['parts'];
        $query->resolve($objectPath, $parsed['alias'] ? $parsed['alias']['name'] : null);
        $last = array_pop($query->from);
        if ($parsed['ref_clause']) {
            $refClause = $this->processMany($parsed['ref_clause'], $query);
            $last['ref_clause'][] = [
                'expr_type' => 'operator',
                'base_expr' => 'AND'
            ];
            foreach ($refClause as $clause) {
                $last['ref_clause'][] = $clause;
            }
        }
        return $last;
    }

    private function subtree($parsed, FastQuery $query)
    {
        if ($parsed['sub_tree']) {
            $parsed['sub_tree'] = $this->processMany($parsed['sub_tree'], $query);
        }
        return $parsed;
    }

    private function subquery($parsed, FastQuery $query)
    {
        if ($parsed['sub_tree']) {
            $parsed['sub_tree'] = $this->compileParsed($parsed['sub_tree']);
        }
        return $parsed;
    }

    private function formatNoQuotes(array $parsed)
    {
        return implode($parsed['delim'], $parsed['parts']);
    }
}
