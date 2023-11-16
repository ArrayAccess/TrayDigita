<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\L10n\PoMo\Metadata;

use ArrayAccess\TrayDigita\Exceptions\Runtime\RuntimeException;
use ArrayAccess\TrayDigita\L10n\PoMo\Helper\PluralParser;
use Throwable;
use function array_pop;
use function sprintf;

final class PluralForm
{
    public const DEFAULT_PLURAL_COUNT = 2;
    public const DEFAULT_EXPRESSION   = 'n != 1';
    public const DEFAULT_PLURAL_FORMS = 'nplurals='
    . self::DEFAULT_PLURAL_COUNT
    . ';plural='
    . self::DEFAULT_EXPRESSION;
    public const HEADER_KEY = 'Plural-Forms';
    public const HEADER_KEY_LOWERCASE = 'plural-forms';

    private ?Throwable $error;

    /**
     * @var ?array|false
     */
    private array|false|null $tokens = null;

    /**
     * @param int $pluralCount
     * @param string $expression
     */
    public function __construct(
        private readonly int $pluralCount,
        private readonly string $expression
    ) {
    }

    /**
     * @return string
     */
    public function getExpression() : string
    {
        return $this->expression;
    }

    /**
     * @return int
     */
    public function getPluralCount() : int
    {
        return $this->pluralCount;
    }

    /**
     * @return array|false
     */
    public function getTokens() : array|false
    {
        if ($this->tokens === null) {
            try {
                $this->tokens = PluralParser::parseFunction($this->expression);
            } catch (Throwable $e) {
                $this->error = $e;
                $this->tokens = false;
            }
        }
        return $this->tokens;
    }

    /**
     * @return bool
     */
    public function valid() : bool
    {
        return $this->getTokens() !== false;
    }

    public function getError() : ?Throwable
    {
        return $this->error;
    }

    /**
     * @param int $n
     *
     * @return int
     * @throws Throwable
     */
    public function execute(int $n) : int
    {
        $tokens = $this->getTokens();
        if ($tokens === false) {
            throw $this->getError();
        }

        $i     = 0;
        $total = count($tokens);
        $stack = [];
        while ($i < $total) {
            $next = $tokens[$i];
            $i++;
            if (PluralParser::PLURAL_VAR === $next[0]) {
                $stack[] = $n;
                continue;
            } elseif (PluralParser::PLURAL_VALUE === $next[0]) {
                $stack[] = $next[1];
                continue;
            }

            $v2      = array_pop($stack);
            $v1      = array_pop($stack);
            $next    = ($next[1]??null);
            // Only operators left.
            switch ($next) {
                case '?:':
                    $v0 = array_pop($stack);
                    $stack[] = $v0 ? $v1 : $v2;
                    break;
                case '%':
                    $stack[] = $v1 % $v2;
                    break;
                case '||':
                    $stack[] = $v1 || $v2;
                    break;
                case '&&':
                    $stack[] = $v1 && $v2;
                    break;
                case '<':
                    $stack[] = $v1 < $v2;
                    break;
                case '<=':
                    $stack[] = $v1 <= $v2;
                    break;
                case '>':
                    $stack[] = $v1 > $v2;
                    break;
                case '>=':
                    $stack[] = $v1 >= $v2;
                    break;
                case '!=':
                    $stack[] = $v1 !== $v2;
                    break;
                case '==':
                    $stack[] = $v1 === $v2;
                    break;
                default:
                    throw new RuntimeException(sprintf('Unknown operator "%s"', $next));
            }
        }

        return (int) $stack[0];
    }

    public function toPluralFormHeaderValue() : string
    {
        return sprintf('nplurals=%d;plural=%s;', $this->pluralCount, $this->expression);
    }

    public function __toString() : string
    {
        return $this->toPluralFormHeaderValue();
    }
}
