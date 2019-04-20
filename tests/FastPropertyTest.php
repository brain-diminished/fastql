<?php

namespace FastQL\Tests;

use FastQL\Internal\FastProperty;
use PHPUnit\Framework\TestCase;

class FastPropertyTest extends TestCase
{
    public function testConstruct()
    {
        $property = new FastProperty('address');
        self::assertFalse($property->nullable());
        self::assertTrue($property->local());
        self::assertEquals('address', $property->__toString());
        $property = new FastProperty('?address');
        self::assertTrue($property->nullable());
        self::assertTrue($property->local());
        self::assertEquals('?address', $property->__toString());
        $property = new FastProperty('\?address');
        self::assertTrue($property->nullable());
        self::assertFalse($property->local());
        self::assertEquals('\?address', $property->__toString());
        $property = new FastProperty('\address');
        self::assertFalse($property->nullable());
        self::assertFalse($property->local());
        self::assertEquals('\address', $property->__toString());
    }

    public function testMutate()
    {
        $property = new FastProperty('address');
        self::assertEquals('address', $property->__toString());
        $property->setNullable(true);
        self::assertEquals('?address', $property->__toString());
        $property->setLocal(false);
        self::assertEquals('\?address', $property->__toString());
        $property->setNullable(false);
        self::assertEquals('\address', $property->__toString());
        $property->setName('shipping_address');
        self::assertEquals('\shipping_address', $property->__toString());
    }
}
