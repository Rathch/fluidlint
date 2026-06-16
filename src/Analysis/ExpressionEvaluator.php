<?php

declare(strict_types=1);

namespace Cru\Fluidlint\Analysis;

/**
 * Evaluates constant Fluid boolean/expression values for static dead-code analysis.
 */
final class ExpressionEvaluator
{
    public function evaluateBoolean(mixed $value): ?bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return (bool)$value;
        }

        if (!is_string($value)) {
            return null;
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return false;
        }

        if (in_array(strtolower($trimmed), ['true', '1', 'yes', 'on'], true)) {
            return true;
        }

        if (in_array(strtolower($trimmed), ['false', '0', 'no', 'off', 'null'], true)) {
            return false;
        }

        return null;
    }

    public function evaluateMixed(mixed $value): mixed
    {
        if (is_scalar($value) || $value === null) {
            return $value;
        }

        if (!is_string($value)) {
            return null;
        }

        $trimmed = trim($value, " \t\n\r\0\x0B'\"");
        if (is_numeric($trimmed)) {
            return str_contains($trimmed, '.') ? (float)$trimmed : (int)$trimmed;
        }

        if (strtolower($trimmed) === 'true') {
            return true;
        }
        if (strtolower($trimmed) === 'false') {
            return false;
        }

        return $trimmed;
    }

    public function valuesEqual(mixed $left, mixed $right): ?bool
    {
        if ($left === null || $right === null) {
            return null;
        }

        if (is_bool($left) || is_bool($right)) {
            $leftBool = $this->evaluateBoolean($left);
            $rightBool = $this->evaluateBoolean($right);
            if ($leftBool === null || $rightBool === null) {
                return null;
            }
            return $leftBool === $rightBool;
        }

        return $left == $right;
    }
}
