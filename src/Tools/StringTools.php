<?php
declare(strict_types=1);

namespace Abcde1410\PDFDecrypter\Tools;

class StringTools
{
    /**
     * Escape characters in the given string
     * 
     * @param string $string String to escape
     * 
     * @return string Escaped string
     */
    public static function escape(string $string): string
    {
        return strtr($string, array(')' => '\\)', '(' => '\\(', '\\' => '\\\\', chr(9) => '\t', chr(10) => '\n', chr(12) => '\f', chr(13) => '\r'));
	}

    /**
     * Unescape characters in the given string
     * 
     * @param string $string String to unescape
     * 
     * @return string Unescaped string
     */
    public static function unescape(string $string): string
    {
		return strtr($string, array('\\)' => ')', '\\(' => '(', '\\\\' => '\\', '\t' => chr(9), '\n' => chr(10), '\f' => chr(12), '\r' => chr(13)));
	}

    /**
     * Normalize given address
     * 
     * @param string $address Address to normalize
     * 
     * @return string The normalized string
     */
	public static function normalizeObjectAddress(string $address): string
	{
		if (!preg_match("/\d+\s+\d+\s*(obj|R)/", $address)) {
			return false;
		}
		$address = str_replace(['R', 'obj'], [' obj', ' obj'], $address);
		if (!preg_match("/\d+\s{1}\d+\s{1}obj/", $address)) {
			$address = preg_replace("/\s+/", ' ', $address);
		}
		return trim($address);
	}

    /**
     * Encode the string using md5 and return its binary representation
     * 
     * @param string $string
     * 
     * @return string
     */
	public static function md5Hex(string $string): string
    {
        return pack('H*', md5($string));
    }
}