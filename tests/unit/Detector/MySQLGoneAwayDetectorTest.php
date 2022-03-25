<?php

namespace Facile\DoctrineMySQLComeBack\Doctrine\DBAL\Detector;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;

class MySQLGoneAwayDetectorTest extends TestCase
{
    use ProphecyTrait;

    private const RETRYABLE_ERROR = 'Lost connection to MySQL server during query is an error not retryable on UPDATE queries';

    private const RETRYABLE_ERROR_OUTSIDE_UPDATE = 'Lost connection to MySQL server during query is an error not retryable on UPDATE queries';

    /**
     * @dataProvider isUpdateQueryDataProvider
     */
    public function testIsUpdateQuery(string $query, bool $isUpdate): void
    {
        $error = new \Exception(self::RETRYABLE_ERROR_OUTSIDE_UPDATE);

        $this->assertSame(! $isUpdate, (new MySQLGoneAwayDetector())->isGoneAwayException($error, $query));
        $this->assertTrue((new MySQLGoneAwayDetector())->isGoneAwayException($error, 'SELECT 1'));
    }

    /**
     * @return array{string, bool}[]
     */
    public function isUpdateQueryDataProvider(): array
    {
        return [
            ['UPDATE ', true],
            ['DELETE ', true],
            ['DELETE ', true],
            ['SELECT ', false],
            ['select ', false],
            ["\n\tSELECT\n", false],
            ['(select ', false],
            [' (select ', false],
            [' 
            (select ', false],
            [' UPDATE WHERE (SELECT ', true],
            [' UPDATE WHERE 
            (select ', true],
        ];
    }
}
