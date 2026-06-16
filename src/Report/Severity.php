<?php

declare(strict_types=1);

namespace Cru\Fluidlint\Report;

enum Severity: string
{
    case Info = 'info';
    case Warning = 'warning';
    case Error = 'error';

    public function rank(): int
    {
        return match ($this) {
            self::Info => 0,
            self::Warning => 1,
            self::Error => 2,
        };
    }

    public function isAtLeast(self $minimum): bool
    {
        return $this->rank() >= $minimum->rank();
    }
}
