<?php
namespace Abcde1410\PDFDecrypter\Encoding\Filters;

interface FilterInterface
{
    public function decode(string $string): string;
    public function encode(string $string): string;
}