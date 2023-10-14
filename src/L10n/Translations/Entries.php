<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\L10n\Translations;

use ArrayAccess\TrayDigita\L10n\Translations\Interfaces\EntryInterface;
use Countable;

class Entries implements Countable
{
    /**
     * @var array<string, EntryInterface>
     */
    protected array $entries = [];

    /**
     * @param EntryInterface $entry
     *
     * @return bool
     */
    public function add(EntryInterface $entry) : bool
    {
        $key = $entry->getId();
        if (!$key) {
            return false;
        }
        $this->entries[$key] = $entry;
        return true;
    }

    public function has(EntryInterface|string $entry) : bool
    {
        return isset($this->entries[$entry instanceof EntryInterface ? $entry->getId() : $entry]);
    }

    public function remove(EntryInterface|string $entry) : bool
    {
        $entry = $entry instanceof EntryInterface ? $entry->getId() : $entry;
        if (!isset($this->entries[$entry])) {
            return false;
        }
        unset($this->entries[$entry]);
        return true;
    }

    /**
     * @param EntryInterface ...$entries
     *
     * @return int
     */
    public function merge(EntryInterface ...$entries) : int
    {
        $added = 0;
        foreach ($entries as $entry) {
            $added += $this->add($entry) ? 1 : 0;
        }
        return $added;
    }

    /**
     * @param EntryInterface|string $entry
     *
     * @return ?EntryInterface
     */
    public function entry(EntryInterface|string $entry) : ?EntryInterface
    {
        $entry = $entry instanceof EntryInterface ? $entry->getId() : $entry;
        return ($this->entries[$entry] ?? null);
    }

    /**
     * @return EntryInterface[]
     */
    public function getEntries() : array
    {
        return $this->entries;
    }

    public function count() : int
    {
        return count($this->entries);
    }

    public function clearAllEntries(): void
    {
        $this->entries = [];
    }

    public function __destruct()
    {
        $this->clearAllEntries();
    }
}
