<?php

namespace Facile\DoctrineMySQLComeBack\Doctrine\DBAL\Detector;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;

class MySQLGoneAwayDetectorTest extends TestCase
{
    use ProphecyTrait;

    private const RETRYABLE_ERROR = 'Lost connection to MySQL server during query is an error not retryable on UPDATE queries';

    private const RETRYABLE_ERROR_OUTSIDE_UPDATE = 'Lost connection to MySQL server during query is an error not retryable on UPDATE queries';

    private const RETRYABLE_ERROR_ON_SENDING_QUERY = 'Warning: Error while sending QUERY packet. PID=34';

    private const RETRYABLE_ERROR_ON_SERVER_GONE = 'ERROR 2006 (HY000): MySQL server has gone away';

    private const NOT_RETRYABLE_ERROR = 'Unknown error';

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
     * @dataProvider isGoneAwayExceptionDataProvider
     */
    public function testIsGoneAwayException(string $message, bool $isUpdate, bool $expectedIsGoneAwayException): void
    {
        $error = new \Exception($message);
        $query = $isUpdate ? 'DELETE FROM table1;' : 'SELECT 1;';

        $this->assertSame(
            $expectedIsGoneAwayException,
            (new MySQLGoneAwayDetector())->isGoneAwayException($error, $query)
        );
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

    /**
     * @return array{0: string, 1: bool, 2: bool}[]
     */
    public function isGoneAwayExceptionDataProvider(): array
    {
        return [
            [self::RETRYABLE_ERROR_ON_SERVER_GONE, true, true],
            [self::RETRYABLE_ERROR_OUTSIDE_UPDATE, true, false],
            [self::RETRYABLE_ERROR_ON_SENDING_QUERY, true, true],
            [self::NOT_RETRYABLE_ERROR, true, false],
            [self::RETRYABLE_ERROR_ON_SERVER_GONE, false, true],
            [self::RETRYABLE_ERROR_OUTSIDE_UPDATE, false, true],
            [self::RETRYABLE_ERROR_ON_SENDING_QUERY, false, true],
            [self::NOT_RETRYABLE_ERROR, false, false],
        ];
    }
}
