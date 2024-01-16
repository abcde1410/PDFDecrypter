<?php
declare(strict_types=1);

namespace Abcde1410\PDFDecrypter\Encoding;

use Abcde1410\PDFDecrypter\Exceptions\PDFDecrypterException;

class FilterFactory
{
    /**
	 * An array of filters instances
	 * @public
	 */
    protected array $filters;

    public function __construct(string|array $filters)
    {
        $filters = is_string($filters) ? [$filters] : $filters;

        foreach ($filters as $filter) {
            $filter = "Abcde1410\\PDFDecrypter\\Encoding\\Filters\\$filter";
            if (!class_exists($filter)) {
                throw new PDFDecrypterException('The file cannot be decrypted because the used encoding filter is unsupported.');
            }
            $this->filters[] = new $filter();
        }
    }

    /**
     * Decode the given string
     * 
     * @param string $string Encoded string
     * 
     * @return string Decoded string
     */
    public function decode(string $string): string
    {
        foreach ($this->filters as $filter) {
            $string = $filter->decode($string);
        }
        return $string;
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
        $reversedFilters = array_reverse($this->filters);
        foreach($reversedFilters as $filter) {
            $string = $filter->encode($string);
        }
        return $string;
    }
}