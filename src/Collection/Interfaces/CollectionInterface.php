<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Collection\Interfaces;

use ArrayAccess;
use Traversable;

interface CollectionInterface extends ArrayAccess, Traversable
{
    public function get($id);

    public function set($id, $value);

    public function remove($id);

    public function has($id) : bool;

    public function contain($param) : bool;

    public function all() : array;

    public function keys() : array;
}
