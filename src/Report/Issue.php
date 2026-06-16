<?php

declare(strict_types=1);

namespace Cru\Fluidlint\Report;

final class Issue
{
    public function __construct(
        public readonly string $ruleId,
        public readonly Severity $severity,
        public readonly string $message,
        public readonly string $file,
        public readonly ?int $line = null,
        public readonly ?int $column = null,
        public readonly array $context = [],
    ) {
    }

    public function toArray(): array
    {
        return [
            'ruleId' => $this->ruleId,
            'severity' => $this->severity->value,
            'message' => $this->message,
            'file' => $this->file,
            'line' => $this->line,
            'column' => $this->column,
            'context' => $this->context,
        ];
    }
}
