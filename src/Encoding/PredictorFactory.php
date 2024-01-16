<?php
declare(strict_types=1);

namespace Abcde1410\PDFDecrypter\Encoding;

use Abcde1410\PDFDecrypter\Exceptions\PDFDecrypterException;
use Abcde1410\PDFDecrypter\Encoding\Predictors\PredictorInterface;

class PredictorFactory
{
    /**
	 * The number of samples in each row of content
	 * @protected
	 */
    protected int $columns;

    /**
	 * The predictor instance
	 * @protected
	 */
    protected PredictorInterface $predictor;

    public function __construct(int $columns, int $predictor)
    {
        if (!$predictor = $this->getPredictorClassName($predictor)) {
            throw new PDFDecrypterException('The file cannot be decrypted because the used predictor is incorrect.');
        }
        
        $predictor = "Abcde1410\\PDFDecrypter\\Encoding\\Predictors\\$predictor";
        if (!class_exists($predictor)) {
            throw new PDFDecrypterException('The file cannot be decrypted because the used predictor is unsupported.');
        }

        $this->predictor = new $predictor();
        $this->columns = $columns + 1;
    }

    protected function getPredictorClassName(int $predictor): string|bool
    {
        switch ($predictor) {
            case 1:
                return 'NoPrediction';
            case 2:
                return 'TIFFPredictor2';
            case 10:
                return 'PNGNoneOnAllRows';
            case 11:
                return 'PNGSubOnAllRows';
            case 12:
                return 'PNGUpOnAllRows';
            case 13:
                return 'PNGAverageOnAllRows';
            case 14:
                return 'PNGPaethOnAllRows';
            case 15:
                return 'PNGOptimum';
            default:
                return false;
        }
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
        return $this->predictor->decode($this->columns, $entry);
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
        return $this->predictor->encode($this->columns, $entries);
    }
}