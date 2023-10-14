<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\L10n\PoMo\Helper;

use ArrayAccess\TrayDigita\Exceptions\Runtime\RuntimeException;
use ArrayAccess\TrayDigita\L10n\PoMo\Metadata\PluralForm;
use function array_pop;
use function intval;
use function preg_match;
use function sprintf;
use function strlen;
use function strspn;
use function substr;
use function trim;

class PluralParser
{
    const PLURAL_OPERATOR = 1;
    const PLURAL_VALUE = 2;
    const PLURAL_VAR = 3;

    /**
     * Operation characters
     */
    const OP_CHARS = '|&><!=%?:';
    const NUMERIC_CHARS = '0123456789';
    const OP_PRECEDENCE = [
        '%'  => 6,
        '<'  => 5,
        '<=' => 5,
        '>'  => 5,
        '>=' => 5,

        '==' => 4,
        '!=' => 4,

        '&&' => 3,

        '||' => 2,

        '?:' => 1,
        '?'  => 1,

        '('  => 0,
        ')'  => 0
    ];

    /**
     * @param string $pluralString
     *
     * @return ?array{"0":int,"1":string}
     */
    public static function getPluralFormDefinitions(string $pluralString) : ?array
    {
        if (!preg_match(
            '~^\s*nplurals=\s*(\d+)\s*;\s*plural\s*=\s*(.+[^;])\s*(?:;+)?\s*$~',
            $pluralString,
            $match
        ) || empty($match)) {
            return null;
        }

        return [
            (int) $match[1],
            $match[2]
        ];
    }

    public static function createPluralFormFromPluralString(string $pluralString) : PluralForm
    {
        $definition = self::getPluralFormDefinitions($pluralString)??[
            PluralForm::DEFAULT_PLURAL_COUNT,
            PluralForm::DEFAULT_EXPRESSION
        ];
        return new PluralForm(
            $definition[0],
            $definition[1]
        );
    }

    /**
     * @param string $pluralString
     *
     * @return array
     * @throws RuntimeException
     */
    public static function parseFunction(string $pluralString) : array
    {
        $pluralString = self::getPluralFormDefinitions($pluralString)[1] ?? $pluralString;
        $pos = 0;
        $pluralString = trim($pluralString);
        $len = strlen($pluralString);
        // Convert infix operators to postfix using the shunting-yard algorithm.
        $output = [];
        $stacks  = [];
        while ($pos < $len) {
            $next = $pluralString[$pos] ?? '';
            switch ($next) {
                // Ignore whitespace.
                case ' ':
                case "\t":
                    $pos++;
                    break;
                // Variable (n).
                case 'n':
                    $output[] = [ self::PLURAL_VAR ];
                    $pos++;
                    break;
                // Parentheses.
                case '(':
                    $stacks[] = $next;
                    $pos++;
                    break;

                case ')':
                    $found = false;
                    while (! empty($stacks)) {
                        $stack = $stacks[ count($stacks) - 1 ];
                        if ('(' !== $stack) {
                            $output[] = [ self::PLURAL_OPERATOR, array_pop($stacks) ];
                            continue;
                        }

                        // Discard open paren.
                        array_pop($stacks);
                        $found = true;
                        break;
                    }

                    if (! $found) {
                        throw new RuntimeException('Mismatched parentheses');
                    }

                    $pos++;
                    break;
                // Operators.
                case '|':
                case '&':
                case '>':
                case '<':
                case '!':
                case '=':
                case '%':
                case '?':
                    $end_operator = strspn($pluralString, self::OP_CHARS, $pos);
                    $operator     = substr($pluralString, $pos, $end_operator);
                    if (! isset(self::OP_PRECEDENCE[$operator])) {
                        throw new RuntimeException(
                            sprintf(
                                'Unknown operator "%s"',
                                $operator
                            )
                        );
                    }
                    $isAssociative = '?:' === $operator || '?' === $operator;
                    while (! empty($stacks)) {
                        $stack = $stacks[count($stacks) - 1];
                        $precedenceOp = self::OP_PRECEDENCE[$operator];
                        $precedenceStack = self::OP_PRECEDENCE[$stack];
                        // Ternary is right-associative in C.
                        if (($isAssociative && $precedenceOp >= $precedenceStack)
                            || (!$isAssociative && $precedenceOp > $precedenceStack)
                        ) {
                            break;
                        }

                        $output[] = [self::PLURAL_OPERATOR, array_pop($stacks)];
                    }
                    $stacks[] = $operator;

                    $pos += $end_operator;
                    break;

                // Ternary "else".
                case ':':
                    $found = false;
                    $s_pos = count($stacks) - 1;
                    while ($s_pos >= 0) {
                        $stack = $stacks[ $s_pos ];
                        if ('?' !== $stack) {
                            $output[] = [ self::PLURAL_OPERATOR, array_pop($stacks) ];
                            $s_pos--;
                            continue;
                        }

                        // Replace.
                        $stacks[ $s_pos ] = '?:';
                        $found           = true;
                        break;
                    }

                    if (! $found) {
                        throw new RuntimeException(
                            'Missing starting "?" ternary operator'
                        );
                    }
                    $pos++;
                    break;
                // Default - number or invalid.
                default:
                    if ($next >= '0' && $next <= '9') {
                        $span     = strspn($pluralString, self::NUMERIC_CHARS, $pos);
                        $output[] = [self::PLURAL_VALUE, intval(substr($pluralString, $pos, $span))];
                        $pos     += $span;
                        break;
                    }
                    throw new RuntimeException(
                        sprintf('Unknown symbol "%s"', $next)
                    );
            }
        }


        while (!empty($stacks)) {
            $stack = array_pop($stacks);
            if ('(' === $stack || ')' === $stack) {
                throw new RuntimeException(
                    'Mismatched parentheses'
                );
            }
            $output[] = [self::PLURAL_OPERATOR, $stack];
        }

        return $output;
    }
}
