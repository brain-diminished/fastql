<?php

namespace FastQL\Tests;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Schema;
use FastQL\Tests\Utils\Csv;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;
use TheCodingMachine\FluidSchema\FluidSchema;

abstract class AbstractFastQLTest extends TestCase
{
    /** @var Connection */
    private static $sharedConnection;

    /** @var Connection */
    protected $connection;

    protected static function getDbSchema(): Schema
    {
        $schema = new FluidSchema(new Schema());
        $schema->table('countries')
            ->column('id')->integer()->primaryKey()->autoIncrement()
            ->column('name')->string()
            ->column('code')->string();
        $schema->table('addresses')
            ->column('id')->integer()->primaryKey()->autoIncrement()
            ->column('country_id')->references('countries')
            ->column('city')->string()
            ->column('zip_code')->string()
            ->column('street')->string()
            ->column('number')->string()->null();
        $schema->table('companies')
            ->column('id')->integer()->primaryKey()->autoIncrement()
            ->column('address_id')->references('addresses')
            ->column('name')->string()
            ->column('website')->string()->null();
        $schema->table('users')
            ->column('id')->integer()->primaryKey()->autoIncrement()
            ->column('address_id')->references('addresses')
            ->column('employer_id')->references('companies')->null()
            ->column('first_name')->string()
            ->column('last_name')->string();
        $schema->table('goods')
            ->column('id')->integer()->primaryKey()->autoIncrement()
            ->column('producer_id')->references('companies')->null()
            ->column('name')->string()
            ->column('description')->string()->null()
            ->column('price')->decimal(10, 2);
        $schema->table('transactions')
            ->column('id')->integer()->primaryKey()->autoIncrement()
            ->column('seller_id')->references('users')
            ->column('buyer_id')->references('users')
            ->column('good_id')->references('goods')
            ->column('date')->date();

        $schema->table('persons')
            ->column('id')->integer()->primaryKey()->autoIncrement()
            ->column('parent_1_id')->references('persons')->null()
            ->column('parent_2_id')->references('persons')->null()
            ->column('first_name')->string()
            ->column('last_name')->string();
        return $schema->getDbalSchema();
    }

    protected static function getDbData(): iterable
    {
        foreach (glob(__DIR__ . '/test_data/*.csv') as $csvFile) {
            yield basename($csvFile, '.csv') => Csv::load($csvFile, true);
        }
        foreach (glob(__DIR__ . '/test_data/*.json') as $jsonFile) {
            $json = json_decode(file_get_contents($jsonFile), true);
            foreach ($json as $key => $collection) {
                yield $key => $collection;
            }
        }
        foreach (glob(__DIR__ . '/test_data/*.yaml') as $yamlFile) {
            $yaml = Yaml::parseFile($yamlFile);
            foreach ($yaml as $key => $collection) {
                yield $key => $collection;
            }
        }
        foreach (glob(__DIR__ . '/test_data/*.php') as $phpFile) {
            $php = (include $phpFile);
            foreach ($php as $key => $collection) {
                yield $key => $collection;
            }
        }
    }

    protected function resetSharedConn()
    {
        if (!self::$sharedConnection) {
            return;
        }

        self::$sharedConnection->close();
        self::$sharedConnection = null;
    }

    protected static function getConnection()
    {
        if(!self::$sharedConnection) {
            self::$sharedConnection = DriverManager::getConnection([
                'driver' => $GLOBALS['db_type'],
                'user' => $GLOBALS['db_username'],
                'password' => $GLOBALS['db_password'],
                'host' => $GLOBALS['db_host'],
                'dbname' => $GLOBALS['db_name'],
                'port' => $GLOBALS['db_port'],
            ]);
        }
        return self::$sharedConnection;
    }

    public static function setUpBeforeClass()/* The :void return type declaration that should be here would cause a BC issue */
    {
        $connection = self::getConnection();
        $current = $connection->getSchemaManager()->createSchema();
        $schema = static::getDbSchema();
        $connection->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach ($schema->getMigrateFromSql($current, $connection->getDatabasePlatform()) as $sql) {
            $connection->exec($sql);
        }
        $connection->exec('SET FOREIGN_KEY_CHECKS = 1');
        self::cleanUp();
        $connection->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach (self::getDbData() as $table => $entries) {
            foreach ($entries as $entry) {
                $connection->insert($table, $entry);
            }
        }
        $connection->exec('SET FOREIGN_KEY_CHECKS = 1');
    }

    public static function tearDownAfterClass()/* The :void return type declaration that should be here would cause a BC issue */
    {
        self::cleanUp();
    }

    protected static function cleanUp()
    {
        $connection = self::getConnection();
        $schema = $connection->getSchemaManager()->createSchema();
        $connection->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach ($schema->getTableNames() as $table) {
            $connection->exec("DELETE FROM $table");
        }
        $connection->exec('SET FOREIGN_KEY_CHECKS = 1');
    }

    protected function setUp(): void
    {
        $this->connection = self::getConnection();
    }

    protected function tearDown(): void
    {
        while ($this->connection->isTransactionActive()) {
            $this->connection->rollBack();
        }
    }
}
