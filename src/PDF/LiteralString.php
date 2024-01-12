<?php
declare(strict_types=1);

namespace Abcde1410\PDFDecrypter\PDF;

use Abcde1410\PDFDecrypter\Tools\StringTools;

class LiteralString extends StringObject
{
    /**
     * Set the string content
     *
     * @param string $content Content of the string
     */
    public function set(string|null $content): void
    {
        $content = $content !== null ? StringTools::unescape($content) : $content;
        $this->content = $content;
    }
}