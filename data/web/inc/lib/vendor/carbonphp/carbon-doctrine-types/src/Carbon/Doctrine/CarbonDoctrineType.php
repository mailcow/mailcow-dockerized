<?php

declare(strict_types=1);

namespace Carbon\Doctrine;

use Carbon\CarbonInterface;
use Doctrine\DBAL\Platforms\AbstractPlatform;

interface CarbonDoctrineType
{
    public function getSQLDeclaration(array $fieldDeclaration, AbstractPlatform $platform): string;

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?CarbonInterface;

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string;
}
