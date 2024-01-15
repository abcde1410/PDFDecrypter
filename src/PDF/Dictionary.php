<?php
declare(strict_types=1);

namespace Abcde1410\PDFDecrypter\PDF;

use ArrayAccess;
use Abcde1410\PDFDecrypter\Exceptions\PDFDecrypterException;

class Dictionary extends StringObject implements ArrayAccess
{
    /**
	 * Array containing dictionary elements
	 * @protected
	 */
    protected array $dictionary;

    public function __construct(string|array $content = null)
    {
        if ($content) {
            $this->set($content);
        }
    }

    /**
     * Set object properties
     *
     * @param string|array $content dictionary array or string
     * 
     * @throws PDFDecrypterException when given content string does not meet the dictionary requirements
     */
    public function set(string|array $content): void
    {
        if (is_string($content)) {
            $content = trim($content);
            if (!str_starts_with($content, '<<') || !str_ends_with($content, '>>') || substr_count($content, '<<') != substr_count($content, '>>')) {
                throw new PDFDecrypterException('The given content does not meet the dictionary requirements');
            }
            parent::set($content);
            $this->dictionary = $this->createArray($this->content);
        }
        elseif (is_array($content)) {
            $this->dictionary = $content;
            $this->content = $this->createString($content);
        }
    }

    /**
     * ArrayAccess method setting value for an offset
     *
     * @param $offset Offset to set value for
     * @param $offset Value to set
     */
    public function offsetSet($offset, $value): void 
    {
        if (is_null($offset)) {
            $this->dictionary[] = $value;
        } else {
            $this->dictionary[$offset] = $value;
        }
        $this->content = $this->createString($this->dictionary);
    }

    /**
     * ArrayAccess method checking if offset exists
     *
     * @param $offset Offset to check
     * 
     * @return bool true if offset exists, false if not
     */
    public function offsetExists($offset): bool 
    {
        return isset($this->dictionary[$offset]);
    }

    /**
     * ArrayAccess method unsetting the given offset
     *
     * @param $offset Offset to unset
     */
    public function offsetUnset($offset): void 
    {
        unset($this->dictionary[$offset]);
        $this->content = $this->createString($this->dictionary);
    }

    /**
     * ArrayAccess method returning value of given offset
     *
     * @param $offset Offset to return
     * 
     * @return mixed value of given offset
     */
    public function offsetGet($offset): mixed 
    {
        return isset($this->dictionary[$offset]) ? $this->dictionary[$offset] : null;
    }

    /**
     * Return dictionary in array form
     * 
     * @return array Dictionary array
     */
    public function getArray(): array
    {
        return $this->dictionary;
    }

    /**
     * Return dictionary in string form
     * 
     * @return string Dictionary string
     */
    public function getString(): string
    {   
        return $this->content;
    }

    /**
     * Create array from given dictionary string
     * 
     * @param string $string Dictionary string
     * 
     * @return array Array containing dictionary elements
     */
    private function createArray(string $string): array
    {
        $array = [];
        if (str_starts_with($string, '<<') || str_starts_with($string, '[')) {
            $offset = str_starts_with($string, '<<') ? 2 : 1;
            $listMode = str_starts_with($string, '<<') ? false : true;
            $dictionary = substr($string, $offset);
            $dictionary = trim(str_replace([chr(13), chr(10)], ['\\r', '\\n'], $dictionary));
            $step = 0;
            $currentArrayKey = $listMode ? 0 : array();
            do {
                if (str_starts_with($dictionary, '\\n') || str_starts_with($dictionary, '>>')) {
                    $dictionary = trim(substr($dictionary, 2));
                    continue;
                }
                if (str_starts_with($dictionary, ']')) {
                    $dictionary = trim(substr($dictionary, 1));
                }

                if ($step%2 == 0) {
                    if (!$listMode) {
                        preg_match("/\/([^\/\s<\(\[]+)(?=\s|\/|<<|>>|\(|\[)/", $dictionary, $matches, PREG_OFFSET_CAPTURE);
                        if (isset($matches[0]) && $matches[0][1] === 0) {
                            $currentArrayKey = $matches[1][0];
                            $dictionary = trim(substr($dictionary, strlen($matches[0][0])));
                        }
                    }
                    $step += 1;
                }
                else {
                    if (str_starts_with($dictionary, '<<')) {
                        preg_match_all("/<<|>>/", $dictionary, $matches, PREG_OFFSET_CAPTURE);
                        $countBrackets = 0;
                        foreach ($matches[0] as $match) {
                            $countBrackets = $match[0] == '<<' ? $countBrackets +1 : $countBrackets -1;
                            if($countBrackets == 0) {
                                $matches[0] = substr($dictionary, 0, $match[1] + 2);
                                $value = $this->createArray($matches[0]);
                                break;
                            }
                        }
                    }
                    elseif (str_starts_with($dictionary, '[')) {
                        preg_match("/\[((\((?>[^\[\)]+|(?R))*\)*|(?>[^\[\]]*)|(?R))+)\]/", $dictionary, $matches);
                        $value = $this->createArray($matches[0]);
                    }
                    elseif (str_starts_with($dictionary, '(')) {
                        preg_match("/(?:\()(.*?)(?<!\\\\)(?:\))/", $dictionary, $matches);
                        $value = new LiteralString($matches[1]);
                    }
                    elseif (str_starts_with($dictionary, '<')) {
                        preg_match("/(?:<)(.+?)(?:>)/", $dictionary, $matches);
                        if (in_array($currentArrayKey, ['O', 'U', 'OE', 'UE'])) {
                            $value = new LiteralString(hex2bin($matches[1]));
                        }
                        else {
                            $value = new HexadecimalString($matches[1]);
                        }
                    }
                    else {
                        preg_match("/(?:\/)?(\d+\s+\d+\s+R|[^\/\s<>\\\\\n\\\\\r]+)(?=\s|\b|\/|<<|>>|\]|\[|\\\\n|\\\\r)/", $dictionary, $matches);
                        $value = $matches[1];
                    }
                    $dictionary = trim(substr($dictionary, strlen($matches[0])));
                    $array[$currentArrayKey] = $value;
                    if ($listMode) {
                        $currentArrayKey += 1;
                    }
                    $step += 1;
                }
            } while (strlen($dictionary) > 0);
        }
        return $array;
    }

    /**
     * Create string from given dictionary array
     * This method is recursive
     * 
     * @param array $array Dictionary array
     * 
     * @return string Dictionary string
     */
    private function createString(array $array): string
    {
        $listMode = array_is_list($array);
        $result = $listMode ? '[' : '<<';
        foreach ($array as $k => $v) {
            if (!$listMode) {
                $result .= ' /' . $k;
            }

            if (is_array($v)) {
                $result .= $this->createString($v);
            }
            else {
                $string = (($v instanceof HexadecimalString) || ($v instanceof LiteralString)) ? $v->content : $v;
                $string = is_bool($string) ? $string : trim((string) $string);
                $result .= $v instanceof HexadecimalString ? ' <' : ($v instanceof LiteralString ? ' (' : (!is_bool($string) && preg_match("/\b(?:\d+\s+\d+\s+R|(?<!\s{1})\d+(?!\s+)|true|false)\b/", $string) ? ' ' : ' /'));
                $result .= $v instanceof HexadecimalString || $v instanceof LiteralString ? $string : $string;
                $result .= $v instanceof HexadecimalString ? '>' : ($v instanceof LiteralString ? ')' : '');
            }
        }
        $result .= $listMode ? ']' : '>>';
        return $result; 
    }
}