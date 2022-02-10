<?php

namespace Facile\DoctrineMySQLComeBack\Doctrine\DBAL\Detector;

class MySQLGoneAwayDetector implements GoneAwayDetector
{
    /** @var string[] */
    protected $goneAwayExceptions = [
        'MySQL server has gone away',
        'Lost connection to MySQL server during query',
    ];

    /** @var string[] */
    protected $goneAwayInUpdateExceptions = [
        'MySQL server has gone away',
    ];

    public function isGoneAwayException(\Throwable $exception, string $sql = null): bool
    {
        if ($sql && $this->isUpdateQuery($sql)) {
            $possibleMatches = $this->goneAwayInUpdateExceptions;
        } else {
            $possibleMatches = $this->goneAwayExceptions;
        }

        $message = $exception->getMessage();

        foreach ($possibleMatches as $goneAwayException) {
            if (str_contains($message, $goneAwayException)) {
                return true;
            }
        }

        return false;
    }

    private function isUpdateQuery(string $sql): bool
    {
        return ! preg_match('/^[\s\n\r\t(]*(select|show|describe)[\s\n\r\t(]+/i', $sql);
    }
}
