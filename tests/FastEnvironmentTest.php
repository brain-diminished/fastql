<?php

namespace FastQL\Tests;

use Doctrine\DBAL\Schema\Schema;
use FastQL\Internal\FastEnvironment;
use PHPUnit\Framework\TestCase;
use TheCodingMachine\FluidSchema\FluidSchema;

class FastEnvironmentTest extends TestCase
{
    public function testPropertyNaming()
    {
        $schema = new FluidSchema(new Schema());
        $schema->table('users')
            ->column('id')->integer()->primaryKey()->autoIncrement()
            ->column('name')->string();
        $schema->table('goods')
            ->column('id')->integer()->primaryKey()->autoIncrement()
            ->column('name')->string()
            ->column('description')->string();
        $schema->table('transactions')
            ->column('id')->integer()->primaryKey()->autoIncrement()
            ->column('__anon__872b7d9f')->references('users', 'fk_transactions__seller')
            ->column('buyer_id')->references('users')
            ->column('id_product')->references('goods')
            ->column('__anon__872b7d9f')->references('goods');
        $environment = new FastEnvironment($schema->getDbalSchema());
        $transactions = $schema->table('transactions')->getDbalTable();
        self::assertTrue($environment->hasProperty($transactions, 'seller'));
        self::assertTrue($environment->hasProperty($transactions, 'buyer'));
        self::assertTrue($environment->hasProperty($transactions, 'product'));
        self::assertTrue($environment->hasProperty($transactions, 'good'));
    }
}
