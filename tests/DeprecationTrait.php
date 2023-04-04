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

    protected function setUp(): void
    {
        Deprecation::enableWithPsrLogger($this->deprecationLogger = new TestLogger());

        parent::setUp();
    }

    protected function tearDown(): void
    {
        $message = '';
        /** @var array{message: string, context: string[]} $deprecation */
        foreach ($this->deprecationLogger->records ?? [] as $deprecation) {
            $message .= $deprecation['message'] . PHP_EOL . print_r($deprecation['context'], true) . PHP_EOL . PHP_EOL;
        }

        if ($message) {
            $this->fail('Test failed due to deprecations: ' . PHP_EOL . $message);
        }

        Deprecation::disable();

        parent::tearDown();
    }
}
