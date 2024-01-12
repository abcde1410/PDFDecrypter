<?php
declare(strict_types=1);

namespace Abcde1410\PDFDecrypter\PDF;

use Abcde1410\PDFDecrypter\Exceptions\PDFDecrypterException;

class StringObject
{
    /**
	 * Content of the string object
	 * @public
	 */
    public string|null $content = '';

    public function __construct(string $content = null)
    {
        if ($content) {
            $this->set($content);
        }
    }

    /**
     * Set the string content
     *
     * @throws PDFDecrypterException when the given content is empty
     * 
     * @param string $content Content of the string
     */
    public function set(string $content): void
    {
        if (empty($content)) {
            throw new PDFDecrypterException('Unable to set content. Given content is empty');
        }
        $this->content = $content;
    }
    
    /**
     * Return the length of the string
	 * 
	 * @return int The length of the string in bytes
     */
    public function length(): int
    {
        return strlen($this->content);
    }

    /**
     * Add string to current content
	 * 
	 * @param string $string String to add to the current content
     */
    public function addToContent(string $string): void
    {
        $this->content .= (string) $string;
    }
}