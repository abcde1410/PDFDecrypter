<?php
declare(strict_types=1);

namespace Abcde1410\PDFDecrypter\Encoding\Predictors;

class PNGUpOnAllRows implements PredictorInterface
{
    /**
     * Decode the given string using the specified predictor
     * 
     * @param int $columns The number of samples in each row of the content
     * @param string $entry Encoded string
     * 
     * @return string Decoded string
     */
    public function decode(int $columns, string $entry): string
    {
        for ($i = $columns; $i < strlen($entry); $i++) {
            if ($i % $columns != 0) {
                $entry[$i] = chr(ord($entry[$i]) + ord($entry[$i-$columns]));
            }
        }
        return $entry;
    }

    /**
     * Encode the given string using the specified predictor
     * 
     * @param int $columns The number of samples in each row of the content
     * @param string $entries Decoded string
     * 
     * @return string Encoded string
     */
    public function encode(int $columns, string $entries): string
    {
        $newEntry = substr($entries, 0, $columns);
        for ($i = $columns; $i < strlen($entries); $i++) {
            if ($i % $columns == 0) {
                $newEntry .= pack('C', 2);
            }
            else {
                $newEntry .= chr(ord($entries[$i]) - ord($entries[$i-$columns]));
            }
        }
        return $newEntry;
    }
}
