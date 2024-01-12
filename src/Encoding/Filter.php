<?php
declare(strict_types=1);

namespace Abcde1410\PDFDecrypter\Encoding;

use Abcde1410\PDFDecrypter\Exceptions\PDFDecrypterException;

class Filter
{   
    /**
	 * Encoding filter name
	 * @protected
	 */
    protected string|array $filter;

    public function __construct(string|array $filter)
    {
        if ($filter != 'FlateDecode') {
            throw new PDFDecrypterException('The file cannot be decrypted because the used encoding filter is unsupported.');
        }
        $this->filter = $filter;
    }

    /**
     * Decode the given string using the specified filter 
     * 
     * @param string $string Encoded string
     * 
     * @return string Decoded string
     */
    public function decode(string $string): string
    {
        switch ($this->filter) {
            case 'FlateDecode':
                return gzuncompress($string);
            break;
        }
    }

    /**
     * Encode the given string using the given filter
     * 
     * @param string $string Decoded string
     * 
     * @return string Encoded string
     */
    public function encode(string $string): string
    {
        switch ($this->filter) {
            case 'FlateDecode':
                return gzcompress($string);
            break;
        }
    }
}