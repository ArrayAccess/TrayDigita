<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Responder\Interfaces;

interface HtmlResponderInterface extends ResponderInterface
{
    public function format(int $code, $data) : string;
}
