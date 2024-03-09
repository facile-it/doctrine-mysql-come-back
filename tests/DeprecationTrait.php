<?php

namespace Facile\DoctrineMySQLComeBack\Tests;

use Doctrine\Deprecations\Deprecation;
use Psr\Log\Test\TestLogger;

/**
 * @psalm-require-extends \PHPUnit\Framework\TestCase
 */
trait DeprecationTrait
{
    private ?TestLogger $deprecationLogger = null;

    private array $ignoredDeprecations = [
        'https://github.com/doctrine/dbal/issues/4966', # public access to Connection::connect, unfixable
        'https://github.com/doctrine/dbal/pull/5383', # savepoint
        'https://github.com/doctrine/dbal/pull/5556', # passing params to Statement::executeQuery
        'https://github.com/doctrine/dbal/pull/5563', # bindParam
        'https://github.com/doctrine/dbal/pull/5699', # use driver middleware to instantiate platform
        'https://github.com/doctrine/dbal/issues/5812', # declare SchemaManagerFactory in config
    ];

    protected function setUp(): void
    {
        Deprecation::enableWithPsrLogger($this->deprecationLogger = new TestLogger());

        parent::setUp();
    }

    protected function tearDown(): void
    {
        $message = '';
        /** @var array{message: string, context: array{file: string, line: int, package: string, link: string}} $deprecation */
        foreach ($this->deprecationLogger->records ?? [] as $deprecation) {
            if (in_array($deprecation['context']['link'], $this->ignoredDeprecations, true)) {
                continue;
            }

            $message .= $deprecation['message'] . PHP_EOL . print_r($deprecation['context'], true) . PHP_EOL . PHP_EOL;
        }

        if ($message !== '') {
            $this->fail('Test failed due to deprecations: ' . PHP_EOL . $message);
        }

        Deprecation::disable();

        parent::tearDown();
    }
}
