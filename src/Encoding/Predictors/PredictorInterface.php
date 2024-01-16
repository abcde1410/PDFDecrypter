<?php
namespace Abcde1410\PDFDecrypter\Encoding\Predictors;

interface PredictorInterface
{
    public function decode(int $columns, string $entry): string;
    public function encode(int $columns, string $entry): string;
}