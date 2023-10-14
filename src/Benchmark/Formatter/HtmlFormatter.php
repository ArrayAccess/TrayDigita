<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Benchmark\Formatter;

use ArrayAccess\TrayDigita\Benchmark\Aggregate\Interfaces\AggregationInterface;
use ArrayAccess\TrayDigita\Benchmark\Aggregate\Interfaces\AggregatorInterface;
use ArrayAccess\TrayDigita\Benchmark\Formatter\Interfaces\FormattedDataInterface;
use ArrayAccess\TrayDigita\Benchmark\Formatter\Interfaces\MetadataCollectionInterface;
use ArrayAccess\TrayDigita\Benchmark\Formatter\Interfaces\MetadataFormatterInterface;
use ArrayAccess\TrayDigita\Benchmark\Interfaces\GroupInterface;
use ArrayAccess\TrayDigita\Benchmark\Interfaces\ProfilerInterface;
use ArrayAccess\TrayDigita\Benchmark\Interfaces\RecordInterface;
use Closure;
use Countable;
use JsonSerializable;
use Psr\Http\Message\StreamInterface;
use ReflectionObject;
use Serializable;
use Traversable;
use function get_class;
use function gettype;
use function spl_object_hash;
use function sprintf;
use function strlen;
use function substr;

class HtmlFormatter implements MetadataFormatterInterface
{
    const BLACKLISTED_OBJECT = [
        ProfilerInterface::class,
        GroupInterface::class,
        RecordInterface::class,
        AggregatorInterface::class,
        AggregationInterface::class,
    ];

    public function isBlacklisted(object $item): bool
    {
        foreach (self::BLACKLISTED_OBJECT as $className) {
            if ($item instanceof $className) {
                return true;
            }
        }
        return false;
    }

    protected function formatObject(
        object $item,
        &$blacklisted = null
    ): string {
        $blacklisted = true;
        if ($this->isBlacklisted($item)) {
            return sprintf(
                'object: (<code>[id=%s]%s</code>)',
                spl_object_hash($item),
                get_class($item)
            );
        }

        $blacklisted = false;
        if (method_exists($item, '__tostring')) {
            $hash = spl_object_hash($item);
            $className = get_class($item);
            $item = (string) $item;
            $length = strlen($item);
            $item = $length > 300
                ? substr($item, 0, 300) . '...' : $item;
            return sprintf(
                'object: (<code>[id=%s]%s</code>)(Stringable(<code>size=%d</code>)) => %s',
                $hash,
                htmlentities($className),
                $length,
                htmlentities(
                    html_entity_decode($item)
                )
            );
        }

        if ($item instanceof JsonSerializable) {
            $json = json_encode($item);
            return sprintf(
                'object: (<code>[id=%s]%s</code>)(JsonSerializable(<code>size=%d</code>)) => %s',
                spl_object_hash($item),
                htmlentities(get_class($item)),
                strlen($json),
                htmlentities($json)
            );
        }

        if ($item instanceof Serializable
            || method_exists($item, '__serialize')
        ) {
            $serialize = serialize($item);
            return sprintf(
                'object: (<code>[id=%s]%s</code>)(Serializable(<code>size=%d</code>)) => %s',
                spl_object_hash($item),
                htmlentities(get_class($item)),
                strlen($serialize),
                htmlentities($serialize)
            );
        }


        $res = null;
        if ($item instanceof Traversable) {
            $res = sprintf('(Traversable(<code>size=%d</code>))', iterator_count($item));
        } elseif ($item instanceof Countable) {
            $res = sprintf('(Countable(<code>size=%d</code>))', count($item));
        } elseif ($item instanceof Closure) {
            $res = '(*Closure)';
        } elseif ($item instanceof StreamInterface) {
            $res = sprintf('(StreamInterface(<code>size=%d</code>))', $item->getSize());
        } else {
            $ref = new ReflectionObject($item);
            if ($ref->isInternal()) {
                $res = '(*Internal)';
            } elseif ($ref->isAnonymous()) {
                $res = '(*Anonymous)';
            }
        }

        $className = htmlentities(get_class($item));
        return sprintf(
            'object: (<code>[id=%s]%s</code>)%s',
            spl_object_hash($item),
            $className,
            $res ? sprintf('(%s)', $res) : ''
        );
    }

    public function format(MetadataCollectionInterface $metadataCollection): FormattedDataInterface
    {
        $formatted = new FormattedData($this);
        foreach ($metadataCollection->getMetadata() as $metadata) {
            $item = $metadata->value();
            if (is_object($item)) {
                $formatted->add($metadata->key(), $this->formatObject($item));
                continue;
            }
            if (is_array($item)) {
                $pre = '';
                foreach ($item as $k => $i) {
                    if ($k === 'password'
                    || is_string($k) && preg_match(
                        // filter
                        '~secret|salt|key|auth|pass|license|hash~',
                        strtolower($k)
                    )) {
                        $i = '<redacted>';
                    }

                    $pre .= "[$k] => ";
                    if (is_scalar($i)) {
                        $type = gettype($i);
                        $i = (string) $i;
                        $length = strlen($i);
                        $i = strlen($i) > 300
                            ? substr($i, 0, 300) . '...' : $i;
                        $pre .= sprintf('%s: (<code>size=%d</code>) %s', $type, $length, htmlentities($i));
                    } elseif (is_array($i)) {
                        $pre .= sprintf('array: (<code>size=%d</code>)', count($i));
                    } elseif (is_object($i)) {
                        $pre .= $this->formatObject($i);
                    } elseif (is_null($i)) {
                        $pre .= 'null';
                    } elseif (is_bool($i)) {
                        $pre .= sprintf('boolean: <code>%s</code>', $i ? 'true' : 'false');
                    } else {
                        $pre .= sprintf('%s: ->', getType($i));
                    }
                    $pre .= "\n";
                }
                $item = sprintf('array: (<code>size=%d</code>)', count($item));
                if ($pre) {
                    $item .= "<pre>$pre</pre>";
                }
                $formatted->add($metadata->key(), $item);
                continue;
            }
            if (is_bool($item)) {
                $formatted->add(
                    $metadata->key(),
                    sprintf('boolean: <code>%s</code>', $item ? 'true' : 'false')
                );
                continue;
            }
            if (is_null($item)) {
                $formatted->add($metadata->key(), 'null');
                continue;
            }
            if (!is_scalar($item)) {
                $formatted->add(
                    $metadata->key(),
                    sprintf('%s: <code>-></code>', getType($item))
                );
                continue;
            }
            if ($metadata->key() === 'password'
                || preg_match(
                    // filter
                    '~secret|salt|key|auth|pass|license|hash~',
                    strtolower($metadata->key())
                )
            ) {
                $item = '<redacted>';
            }
            $formatted->add(
                $metadata->key(),
                htmlentities(html_entity_decode((string)$item))
            );
        }

        return $formatted;
    }
}
