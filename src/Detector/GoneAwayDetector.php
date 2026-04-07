<?php

declare(strict_types=1);

namespace Facile\DoctrineMySQLComeBack\Doctrine\DBAL\Detector;

interface GoneAwayDetector
{
    public function isGoneAwayException(\Throwable $exception, ?string $sql = null): bool;
}
