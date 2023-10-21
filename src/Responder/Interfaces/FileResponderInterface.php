<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Responder\Interfaces;

use Psr\Http\Message\ServerRequestInterface;
use SplFileInfo;

interface FileResponderInterface
{
    public function __construct(SplFileInfo|string $file);

    public function getFile() : SplFileInfo;

    public function valid() : bool;

    public function setAllowRange(bool $enable);

    public function isAllowRange() : bool;

    public function setMaxRanges(int $ranges);
    /**
     * @return int
     */
    public function getMaxRanges() : int;

    public function setAttachmentFileName(string $fileName);

    public function getAttachmentFileName();

    public function resetFileName();

    public function sendLastModifiedTime(bool $enable);

    public function isSendLastModifiedTime() : bool;

    public function sendAsAttachment(bool $enable);

    public function isSendAsAttachment(): bool;

    public function sendRealMimeType(bool $enable);

    public function isSendRealMimeType(): bool;

    public function sendContentLength(bool $enable);

    public function isSendContentLength(): bool;

    public function getBoundary(): string;

    public function send(?ServerRequestInterface $request = null) : never;
}
