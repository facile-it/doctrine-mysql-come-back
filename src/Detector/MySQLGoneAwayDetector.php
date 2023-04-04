<?php

namespace Facile\DoctrineMySQLComeBack\Doctrine\DBAL\Detector;

class MySQLGoneAwayDetector implements GoneAwayDetector
{
    /** @var string[] */
    protected array $goneAwayExceptions = [
        'MySQL server has gone away',
        'Lost connection to MySQL server during query',
        'Error while sending QUERY packet',
    ];

    /** @var string[] */
    protected array $goneAwayInUpdateExceptions = [
        'MySQL server has gone away',
        'Error while sending QUERY packet',
    ];

    public function isGoneAwayException(\Throwable $exception, string $sql = null): bool
    {
        if ($this->isSavepoint($sql)) {
            return false;
        }

        if ($this->isUpdateQuery($sql)) {
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

    private function isUpdateQuery(?string $sql): bool
    {
        return $sql && ! preg_match('/^[\s\n\r\t(]*(select|show|describe)[\s\n\r\t(]+/i', $sql);
    }

    /**
     * @see \Doctrine\DBAL\Platforms\AbstractPlatform::createSavePoint
     */
    private function isSavepoint(?string $sql): bool
    {
        return $sql && str_starts_with(trim($sql), 'SAVEPOINT');
    }
}
