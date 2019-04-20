<?php

namespace FastQL\Tests;

use FastQL\FastCompiler;
use PHPSQLParser\PHPSQLParser;
use Symfony\Component\Yaml\Yaml;

class FastCompilerTest extends AbstractFastQLTest
{
    /** @var FastCompiler */
    private $compiler;

    protected function setUp(): void
    {
        parent::setUp();
        $schema = $this->connection->getSchemaManager()->createSchema();
        $this->compiler = new FastCompiler($schema);
    }

    /**
     * @throws \Exception
     */
    public function testDetectTable()
    {
        $fastql = <<<FASTQL
SELECT users.*
FASTQL;
        $sql = <<<SQL
SELECT users.*
FROM users;
SQL;
        $this->doTest($fastql, $sql);
    }

    public function testDeclare()
    {
        $fastql = <<<FASTQL
SELECT users.*
FROM users
JOIN users.address.country country
WHERE country.code = "ES"
FASTQL;
        $sql = <<<SQL
SELECT users.*
FROM users
JOIN addresses adress ON users.address_id = adress.id
JOIN countries country ON adress.country_id = country.id
WHERE country.code = "ES"
SQL;
        $this->doTest($fastql, $sql);
    }

    public function testNoDeclare()
    {
        $fastql = <<<FASTQL
SELECT users.*
FROM users.address.country country
WHERE country.code = "ES"
FASTQL;
        $sql = <<<SQL
SELECT users.*
FROM users
JOIN addresses adress ON users.address_id = adress.id
JOIN countries country ON adress.country_id = country.id
WHERE country.code = "ES"
SQL;
        $this->doTest($fastql, $sql);
    }

    /**
     * @throws \Exception
     */
    public function testAutoJoinInWhere()
    {
        $fastql = <<<FASTQL
SELECT users.*
WHERE users.address.country.code = "FR"
FASTQL;
        $sql = <<<SQL
SELECT users.*
FROM users
JOIN addresses adress ON users.address_id = adress.id
JOIN countries country ON adress.country_id = country.id
WHERE country.code = "FR"
SQL;
        $this->doTest($fastql, $sql);
    }

    public function testAutojoinInSelect()
    {
        $fastql = <<<FASTQL
SELECT DISTINCT users.address.country.*
FASTQL;
        $sql = <<<SQL
SELECT DISTINCT country.*
FROM users
JOIN addresses address ON users.address_id = address.id
JOIN countries country ON address.country_id = country.id
SQL;
        $this->doTest($fastql, $sql);
    }

    /**
     * @throws \Exception
     */
    public function testAutojoinInOrder()
    {
        $fastql = <<<FASTQL
SELECT users.*
ORDER BY users.address.country.code DESC, users.id ASC
FASTQL;
        $sql = <<<SQL
SELECT users.*
FROM users
JOIN addresses adress ON users.address_id = adress.id
JOIN countries country ON adress.country_id = country.id
ORDER BY country.code DESC, users.id ASC
SQL;
        $this->doTest($fastql, $sql);
    }

    public function testCustomJoinClause()
    {
        $fastql = <<<FASTQL
SELECT users.*
FROM users
JOIN users.address.country ON users.address.country.code != "IT"
FASTQL;
        $sql = <<<SQL
SELECT users.*
FROM users
JOIN addresses adress ON users.address_id = adress.id
JOIN countries country ON adress.country_id = country.id AND country.code != "IT"
SQL;
        $this->doTest($fastql, $sql);
    }

    public function testReverseJoinImplicit()
    {
        $fastql = <<<FASTQL
SELECT `users`.*
FROM `users`.`\seller` `transaction`
FASTQL;
        $sql = <<<FASTQL
SELECT `users`.*
FROM `users`
JOIN `transactions` `transaction` ON `users`.`id` = `transaction`.`seller_id`
FASTQL;
        $this->doTest($fastql, $sql);
    }

    public function testReverseJoinExplicit()
    {
        $fastql = <<<FASTQL
SELECT `users`.*
FROM `users`.`\seller`.`transactions` `transaction`
FASTQL;
        $sql = <<<FASTQL
SELECT `users`.*
FROM `users`
JOIN `transactions` `transaction` ON `users`.`id` = `transaction`.`seller_id`
FASTQL;
        $this->doTest($fastql, $sql);
    }

    public function testReverseJoinExplicitChained()
    {
        $fastql = <<<FASTQL
SELECT goods.*
WHERE goods.\good.transactions.buyer.address.country.code = "ES" OR goods.\good.buyer.address.country.code = "IT"
FASTQL;
        $sql = <<<SQL
SELECT goods.*
FROM goods
JOIN transactions transaction ON goods.id = transaction.good_id
JOIN users customer ON transaction.buyer_id = customer.id
JOIN addresses address ON customer.address_id = address.id
JOIN countries country ON address.country_id = country.id
WHERE country.code = "ES" OR country.code = "IT"
SQL;
        $this->doTest($fastql, $sql);
    }

    public function testReverseJoinImplicitChained()
    {
        $fastql = <<<FASTQL
SELECT goods.*
WHERE goods.\good.buyer.address.country.code = "ES"
FASTQL;
        $sql = <<<SQL
SELECT goods.*
FROM goods
JOIN transactions transaction ON goods.id = transaction.good_id
JOIN users customer ON transaction.buyer_id = customer.id
JOIN addresses address ON customer.address_id = address.id
JOIN countries country ON address.country_id = country.id
WHERE country.code = "ES"
SQL;
        $this->doTest($fastql, $sql);
    }

    /**
     * @throws \Exception
     */
    public function testFunction()
    {
        $fastql = <<<FASTQL
SELECT CONCAT(users.address.number, ", ", users.address.street, " -> ", customer_address.number, ", ", customer_address.street) shipments
FROM `users`.`\seller`.buyer.address customer_address
FASTQL;
        $sql = <<<SQL
SELECT CONCAT(seller_address.number, ", ", seller_address.street, " -> ", customer_address.number, ", ", customer_address.street) shipments
FROM users
JOIN transactions t ON users.id = t.seller_id
JOIN users customer ON t.buyer_id = customer.id
JOIN addresses customer_address ON customer.address_id = customer_address.id
JOIN addresses seller_address ON users.address_id = seller_address.id
SQL;
        $this->doTest($fastql, $sql);
    }

    public function testSubQuery()
    {
        $fastql = <<<FASTQL
SELECT users.*
FROM users
WHERE users.id NOT IN
(
  SELECT users.id
  FROM users
  WHERE users.address.number = 66
)
FASTQL;
        $sql = <<<SQL
SELECT users.*
FROM users
WHERE users.id NOT IN
(
  SELECT users.id
  FROM users
  JOIN addresses a ON users.address_id = a.id
  WHERE a.number = 66
)
SQL;
        $this->doTest($fastql, $sql);
    }

    public function testGroup()
    {
        $fastql = <<<FASTQL
SELECT users.address.country.code, COUNT(users.id)
GROUP BY users.address.country.code
ORDER BY COUNT(users.id) DESC, users.address.country.code DESC
FASTQL;
        $sql = <<<SQL
SELECT country.code, COUNT(users.id)
FROM users
JOIN addresses address ON users.address_id = address.id
JOIN countries country ON address.country_id = country.id
GROUP BY country.code
ORDER BY COUNT(users.id) DESC, country.code DESC
SQL;
        $this->doTest($fastql, $sql);
    }

    public function testGroupConcat()
    {
        $fastql = <<<FASTQL
SELECT GROUP_CONCAT(users.first_name ORDER BY users.first_name SEPARATOR " - ") compatriots, users.address.country.code country
GROUP BY users.address.country.code
ORDER BY COUNT(users.id) DESC, users.address.country.code DESC
FASTQL;
        $sql = <<<SQL
SELECT GROUP_CONCAT(users.first_name ORDER BY users.first_name SEPARATOR " - ") compatriots, country.code country
FROM users
JOIN addresses address ON users.address_id = address.id
JOIN countries country ON address.country_id = country.id
GROUP BY country.code
ORDER BY COUNT(users.id) DESC, country.code DESC
SQL;
        $this->doTest($fastql, $sql);
    }

    public function testSolveAmbiguityExplicit1()
    {
        $fastql = <<<FASTQL
SELECT
  CONCAT(address.number, ", ", address.street) `address`,
  GROUP_CONCAT(residents.first_name SEPARATOR ", ") `residents`
FROM addresses address
JOIN address.`\address`.users residents
GROUP BY address.id
FASTQL;
        $this->doTest($fastql);
    }

    public function testSolveAmbiguityExplicit2()
    {
        $fastql = <<<FASTQL
SELECT
  CONCAT(address.number, ", ", address.street) `address`,
  GROUP_CONCAT(companies.name SEPARATOR ", ") `companies`
FROM addresses address
JOIN address.`\address`.companies companies
GROUP BY address.id
FASTQL;
        $this->doTest($fastql);
    }

    public function testSolveAmbiguityImplicit1()
    {
        $fastql = <<<FASTQL
SELECT
  CONCAT(user_address.number, ", ", user_address.street) `user_address`,
  CONCAT(employer_address.number, ", ", employer_address.street) `employer_address`
FROM addresses user_address
JOIN user_address.`\?address`.employer.address employer_address
FASTQL;
        $this->doTest($fastql);
    }

    public function testSolveAmbiguityImplicit2()
    {
        $fastql = <<<FASTQL
SELECT
  CONCAT(company_address.number, ", ", company_address.street) `company_address`,
  GROUP_CONCAT(products.name SEPARATOR ", ") `products`
FROM addresses company_address
JOIN company_address.`\?address`.`\producer` products
GROUP BY company_address.id
FASTQL;
        $this->doTest($fastql);
    }

    public function testNullable()
    {
        $fastql = <<<FASTQL
SELECT persons.*
FROM persons
WHERE persons.`?parent_1`.id IS NULL
ORDER BY persons.first_name
FASTQL;
        $expected = [
            ['first_name' => 'Ida'],
            ['first_name' => 'Sharon'],
            ['first_name' => 'Victor'],
            ['first_name' => 'Walter'],
        ];
        $this->doTest($fastql, $expected);
    }

    public function testNotNullable()
    {
        $fastql = <<<FASTQL
SELECT persons.*
FROM persons
WHERE persons.`parent_1`.id IS NULL
ORDER BY persons.first_name
FASTQL;
        $expected = [];
        $this->doTest($fastql, $expected);
    }

    public function testReverseNullable()
    {
        $fastql = <<<FASTQL
SELECT *
WHERE countries.\?country.addresses.id IS NULL
FASTQL;
        $sql = <<<SQL
SELECT *
FROM countries
LEFT JOIN addresses address ON countries.id = address.country_id
WHERE address.id IS NULL
SQL;
        $this->doTest($fastql, $sql);
    }

    public function testReverseNotNullable()
    {
        $fastql = <<<FASTQL
SELECT *
WHERE countries.\country.addresses.id IS NULL
FASTQL;
        $expected = [];
        $this->doTest($fastql, $expected);
    }

    public function testMultipleReusePartialPath()
    {
        $fastql = <<<FASTQL
SELECT company.name
FROM companies company
WHERE company.address.country.code != "FR"
AND company.address.number != "66"
AND company.`\producer`.goods.`\good`.buyer.address.country.code = "IT"
AND company.`\producer`.`\good`.transactions.seller.address.country.code = "ES"
FASTQL;
        $sql = $this->compiler->compile($fastql);
        $parser = new PHPSQLParser();
        $parsed = $parser->parse($sql);
        self::assertTrue(count($parsed['FROM']) === 11);
    }

    public function testJoinParent()
    {
        $fastql = <<<FASTQL
SELECT persons.first_name children, persons.parent_1.first_name fathers
ORDER BY persons.parent_1.first_name ASC, persons.first_name ASC
FASTQL;
        $expected = [
            ['children' => 'Dewey', 'fathers' => 'Hal'],
            ['children' => 'Malcolm', 'fathers' => 'Hal'],
            ['children' => 'Reese', 'fathers' => 'Hal'],
            ['children' => 'Lois', 'fathers' => 'Victor'],
            ['children' => 'Hal', 'fathers' => 'Walter'],
        ];
        $this->doTest($fastql, $expected);
    }

    public function testJoinGrandParents()
    {
        $fastql = <<<FASTQL
SELECT
  persons.first_name grand_child,
  persons.parent_1.parent_1.first_name paternal_grand_father,
  persons.parent_1.parent_2.first_name paternal_grand_mother,
  persons.parent_2.parent_1.first_name maternal_grand_father,
  persons.parent_2.parent_2.first_name maternal_grand_mother
ORDER BY persons.first_name
FASTQL;
        $expected = [
            [
                'grand_child' => 'Dewey',
                'paternal_grand_father' => 'Walter',
                'paternal_grand_mother' => 'Sharon',
                'maternal_grand_father' => 'Victor',
                'maternal_grand_mother' => 'Ida',
            ],
            [
                'grand_child' => 'Malcolm',
                'paternal_grand_father' => 'Walter',
                'paternal_grand_mother' => 'Sharon',
                'maternal_grand_father' => 'Victor',
                'maternal_grand_mother' => 'Ida',
            ],
            [
                'grand_child' => 'Reese',
                'paternal_grand_father' => 'Walter',
                'paternal_grand_mother' => 'Sharon',
                'maternal_grand_father' => 'Victor',
                'maternal_grand_mother' => 'Ida',
            ],
        ];
        $this->doTest($fastql, $expected);
    }

    public function testJoinUpAndDown()
    {
        $fastql = <<<FASTQL
SELECT DISTINCT persons.first_name parent_1, persons.\parent_1.parent_2.first_name parent_2
ORDER BY parent_1 ASC, parent_2 ASC
FASTQL;
        $expected = [
            ['parent_1' => 'Hal', 'parent_2' => 'Lois'],
            ['parent_1' => 'Victor', 'parent_2' => 'Ida'],
            ['parent_1' => 'Walter', 'parent_2' => 'Sharon'],
        ];
        $this->doTest($fastql, $expected);
    }

    public function testJoinNoCondition()
    {
        $fastql = <<<FASTQL
SELECT *
FROM users user1
JOIN users user2
FASTQL;
        $this->doTest($fastql, $fastql);
    }

    private function doTest(string $fastql, $expected = null)
    {
        $sql = $this->compiler->compile($fastql);
        self::assertTrue(true, 'compilation succeeded');

        echo $sql.PHP_EOL;

        $result = (array)$this->connection->fetchAll($sql);
        echo Yaml::dump($result, 1);
        self::assertTrue(true, 'Execution succeeded');

        if (is_string($expected)) {
            $expected = (array)$this->connection->fetchAll($expected);
        }
        if (is_array($expected)) {
            $reducedResult = array_map(function ($sub, $i) use ($expected) {
                if (isset($expected[$i])) {
                    return array_intersect_key($sub, $expected[$i]);
                } else if (isset($expected[0])) {
                    return array_intersect_key($sub, $expected[0]);
                } else {
                    return $sub;
                }
            }, $result, array_keys($result));
            self::assertEquals($expected, $reducedResult);
        }
    }
}
