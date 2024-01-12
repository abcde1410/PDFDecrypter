<?php
declare(strict_types=1);

namespace Abcde1410\PDFDecrypter\Encoding;

use Abcde1410\PDFDecrypter\Exceptions\PDFDecrypterException;

class Predictor
{
    /**
	 * The number of samples in each row of content
	 * @protected
	 */
    protected int $columns;

    /**
	 * The code of used prediction algorithm
	 * @protected
	 */
    protected int $prediction;

    public function __construct(int $columns, int $prediction)
    {
        if ($prediction != 12) {
            throw new PDFDecrypterException('The file cannot be decrypted because the used predictor is unsupported.');
        }
        $this->columns = $columns + 1;
        $this->prediction = $prediction;
    }

    /**
     * Decode the given string using the specified predictor
     * 
     * @param string $entry Encoded string
     * 
     * @return string Decoded string
     */
    public function decode(string $entry): string
    {
        switch ($this->prediction) {
            case 12:
                for ($i = $this->columns; $i < strlen($entry); $i++) {
                    if ($i % $this->columns != 0) {
                        $entry[$i] = chr(ord($entry[$i]) + ord($entry[$i-$this->columns]));
                    }
                }
            break;
        }
        return $entry;
    }

    /**
     * Encode the given string using the specified predictor
     * 
     * @param string $entry Decoded string
     * 
     * @return string Encoded string
     */
    public function encode(string $entries): string
    {
        switch ($this->prediction) {
            case 12:
                $newEntry = substr($entries, 0, $this->columns);
                for ($i = $this->columns; $i < strlen($entries); $i++) {
                    if ($i % $this->columns == 0) {
                        $newEntry .= pack('C', 2);
                    }
                    else {
                        $newEntry .= chr(ord($entries[$i]) - ord($entries[$i-$this->columns]));
                    }
                }
            break;
        }
        return $newEntry;
    }
}