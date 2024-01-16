<?php
declare(strict_types=1);

namespace Abcde1410\PDFDecrypter\Encoding\Filters;

class FlateDecode implements FilterInterface
{
    /**
     * Decode the given string
     * 
     * @param string $string Encoded string
     * 
     * @return string Decoded string
     */
    public function decode(string $string): string
    {
        return gzuncompress($string);
    }

    /**
     * Encode the given string
     * 
     * @param string $string Decoded string
     * 
     * @return string Encoded string
     */
    public function encode(string $string): string
    {
        return gzcompress($string);
    }
}
