<?php
declare(strict_types=1);

namespace Abcde1410\PDFDecrypter\Encoding\Predictors;

class NoPrediction implements PredictorInterface
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
        return $entries;
    }
}
