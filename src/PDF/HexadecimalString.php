<?php
declare(strict_types=1);

namespace Abcde1410\PDFDecrypter\PDF;

class HexadecimalString extends StringObject
{
    /**
     * Set the string content
     *
     * @param string $content Content of the string
     */
    public function set(string $content): void
    {
        $content = !ctype_xdigit($content) ? bin2hex($content) : $content;
        $content = strtoupper($content);
        parent::set($content);
    }

    /**
     * Return the hexadecimal representation of the string content
     *
     * @param string $content Content of the object
	 * 
	 * @return string Hexadecimal representation of the string content
     */
    public function hex(): string
    {
        if (!ctype_xdigit($this->content)) {
            return bin2hex($this->content);
        }
        return $this->content;
    }

    /**
     * Return the binary representation of the string content
     *
     * @param string $content Content of the object
	 * 
	 * @return string Binary representation of the string content
     */
    public function bin(): string
    {
        if (ctype_xdigit($this->content)) {
            return hex2bin($this->content);
        }
        return $this->content;
    }
}