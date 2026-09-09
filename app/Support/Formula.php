<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;

/**
 * Evaluate an arithmetic expression.
 *
 * Salary components with a formula were evaluated by eval(), behind a regex
 * that allowed only digits and operators. That regex was the only thing
 * between a payroll formula and arbitrary code, and anything it rejected —
 * a placeholder left unsubstituted because its value was missing, say —
 * silently evaluated to zero and paid the employee nothing for that
 * component.
 *
 * This parses instead: numbers, + - * /, parentheses and unary minus. An
 * expression it cannot evaluate raises, so payroll stops rather than paying
 * a number nobody chose.
 */
final class Formula
{
    private const PRECEDENCE = ['+' => 1, '-' => 1, '*' => 2, '/' => 2];

    public static function evaluate(string $expression): float
    {
        $tokens = self::tokenize($expression);

        if ($tokens === []) {
            throw new InvalidArgumentException('The formula is empty.');
        }

        return self::compute(self::toPostfix($tokens), $expression);
    }

    /**
     * @return list<string>
     */
    private static function tokenize(string $expression): array
    {
        $tokens = [];
        $length = strlen($expression);
        $previous = null;

        for ($i = 0; $i < $length; $i++) {
            $char = $expression[$i];

            if (ctype_space($char)) {
                continue;
            }

            if (ctype_digit($char) || $char === '.') {
                $number = '';

                while ($i < $length && (ctype_digit($expression[$i]) || $expression[$i] === '.')) {
                    $number .= $expression[$i];
                    $i++;
                }

                $i--;

                if (! is_numeric($number)) {
                    throw new InvalidArgumentException("'{$number}' is not a number.");
                }

                $tokens[] = $number;
                $previous = 'number';

                continue;
            }

            if ($char === '(' || $char === ')') {
                $tokens[] = $char;
                $previous = $char;

                continue;
            }

            if (! isset(self::PRECEDENCE[$char])) {
                throw new InvalidArgumentException("'{$char}' is not allowed in a formula.");
            }

            // A minus that opens the expression, or follows an operator or an
            // opening bracket, negates what comes next rather than subtracting.
            if ($char === '-' && ($previous === null || $previous === '(' || isset(self::PRECEDENCE[$previous]))) {
                $tokens[] = '0';
            }

            $tokens[] = $char;
            $previous = $char;
        }

        return $tokens;
    }

    /**
     * @param  list<string>  $tokens
     * @return list<string>
     */
    private static function toPostfix(array $tokens): array
    {
        $output = [];
        $operators = [];

        foreach ($tokens as $token) {
            if (is_numeric($token)) {
                $output[] = $token;

                continue;
            }

            if ($token === '(') {
                $operators[] = $token;

                continue;
            }

            if ($token === ')') {
                while ($operators !== [] && end($operators) !== '(') {
                    $output[] = array_pop($operators);
                }

                if ($operators === []) {
                    throw new InvalidArgumentException('The brackets in the formula do not match.');
                }

                array_pop($operators);

                continue;
            }

            while (
                $operators !== []
                && end($operators) !== '('
                && self::PRECEDENCE[end($operators)] >= self::PRECEDENCE[$token]
            ) {
                $output[] = array_pop($operators);
            }

            $operators[] = $token;
        }

        while ($operators !== []) {
            $operator = array_pop($operators);

            if ($operator === '(') {
                throw new InvalidArgumentException('The brackets in the formula do not match.');
            }

            $output[] = $operator;
        }

        return $output;
    }

    /**
     * @param  list<string>  $postfix
     */
    private static function compute(array $postfix, string $expression): float
    {
        $stack = [];

        foreach ($postfix as $token) {
            if (is_numeric($token)) {
                $stack[] = (float) $token;

                continue;
            }

            $right = array_pop($stack);
            $left = array_pop($stack);

            if ($right === null || $left === null) {
                throw new InvalidArgumentException("'{$expression}' is not a complete expression.");
            }

            if ($token === '/' && $right === 0.0) {
                throw new InvalidArgumentException("'{$expression}' divides by zero.");
            }

            $stack[] = match ($token) {
                '+' => $left + $right,
                '-' => $left - $right,
                '*' => $left * $right,
                '/' => $left / $right,
            };
        }

        if (count($stack) !== 1) {
            throw new InvalidArgumentException("'{$expression}' is not a complete expression.");
        }

        return $stack[0];
    }
}
