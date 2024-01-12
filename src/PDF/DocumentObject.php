<?php
declare(strict_types=1);

namespace Abcde1410\PDFDecrypter\PDF;

use Abcde1410\PDFDecrypter\Tools\StringTools;
use Abcde1410\PDFDecrypter\Exceptions\PDFDecrypterException;

class DocumentObject
{
	/**
	 * The address of the document object
	 * @public
	 */
    public string|null $address;

	/**
	 * The value of the document object when object does not containg dictionary and stream
	 * @public
	 */
	public string|int|null $value;

	/**
	 * Position of the object in the document body
	 * @public
	 */
	public int|null $offset;

	/**
	 * Object dictionary
	 * @public
	 */
	public Dictionary|null $dictionary;

	/**
	 * Object stream
	 * @public
	 */
	public string|null $stream;

	/**
	 * Length of object additional padding
	 * @public
	 */
	public int|null $paddingLength;

	/**
     * Extract the object number from the object address
     *
     * @param string $address Object address
	 * 
	 * @return string The number of the object
     */
    public static function getNumber(string $address): string
    {
        return explode(' ', $address)[0];
    }

	public function __construct(string $address = null, string $content = null, int $offset = null)
	{
		if ($address) {
			$this->setAddress($address);
		}
		if ($content) {
			$this->set($content);
		}
		if ($offset) {
			$this->setOffset($offset);
		}
	}

	/**
     * Set the document object
     *
     * @param string $content Content of the object
	 * 
	 * @return self
     */
	public function set(string $content): self
	{
		$content = trim($content);
		if ($dictionary = $this->findDictionary($content)) {
			$this->setDictionary($dictionary);
		}
		if ($stream = $this->findStream($content)) {
			$this->setStream($stream);
		}

		if (!$dictionary && !$stream) {
			$value = $this->findValue($content);
			$this->setValue($value);
		}
		return $this;
	}

	/**
     * Set the object address
     *
     * @param string $address 
	 * 
	 * @return self
     * 
     * @throws PDFDecrypterException when the given address is incorrect
     */
	public function setAddress(string $address): self
	{
		if (!in_array($address, ['xref', 'trailer', 'startxref']) && !preg_match("/\bxref\d{7}\b|\btrailer\d{7}\b|\bstartxref\d{7}\b/i", $address) && !$address = StringTools::normalizeObjectAddress($address)) {
			throw new PDFDecrypterException('Cannot add object address. Object address ' . $address . ' is incorrect');
		}
		$this->address = trim($address);
		return $this;
	}

	/**
     * Set the object dictionary
     *
     * @param string|Dictionary $dictionary
	 * 
	 * @return self
     */
	public function setDictionary(string|Dictionary $dictionary): self
	{
		if (!($dictionary instanceof Dictionary)) {
			$dictionary = new Dictionary($dictionary);
		}
		$this->dictionary = $dictionary;
		return $this;
	}

	/**
     * Set the object stream
     *
     * @param string $stream 
	 * 
	 * @return self
     */
	public function setStream(string $stream): self
	{
		$this->stream = $stream;
		return $this;
	}

	/**
     * Set the object offset
     *
     * @param int $offset 
	 * 
	 * @return self
     */
	public function setOffset(int $offset): self
	{
		$this->offset = $offset;
		return $this;
	}

	/**
     * Set the object additional padding
     *
     * @param int $length
	 * 
	 * @return self
     */
	public function setPadding(int $length): self
	{
		$this->paddingLength = $length;
		return $this;
	}

	/**
     * Set the object value
     *
     * @param string|int $value
	 * 
	 * @return self
     */
	public function setValue(string|int $value): self
	{
		$this->value = is_string($value) ? trim($value) : $value;
		return $this;
	}

	/**
     * Find the value in the given object body
     *
     * @param string $content Object body
	 * 
	 * @return string Value of the object
     */
	protected function findValue(string $content): string
	{
		$value = preg_replace("/\b\d+\s+\d+\s*obj\b|\bendobj\b|\btrailer\b|\bstartxref\b|\bxref\b|%%EOF\b/i", '', $content);
		return $value;
	}

	/**
     * Find the dictionary in the given object body
     *
     * @param string $content Object body
	 * 
	 * @return Dictionary|bool Object dictionary or false when dictionary has not been found
     */
	protected function findDictionary($content): Dictionary|bool
	{
		$offset = strpos($content, '<<');
		$offset = $offset !== false ? $offset : 0;
		$dictionary = substr($content, $offset);
		if (preg_match_all("/<<|>>/", $dictionary, $matches, PREG_OFFSET_CAPTURE)) {
			$count = 0;
			foreach($matches[0] as $match) {
				if ($match[0] == '<<') {
					$count += 1;
				}
				else {
					$count -= 1;
				}
				if ($count == 0) {
					$dictionary = substr($dictionary, 0, $match[1] + 2);
					break;
				}
			}
			return new Dictionary($dictionary);
		}
		return false;
	}

	/**
     * Find the dictionary in the given object body
     *
     * @param string $content Object body
	 * 
	 * @return string|bool Object stream or false when the stream has not been found
     */
	protected function findStream($content): string|bool
	{
		if (preg_match("/(\bstream\r?\n)/", $content, $startStream, PREG_OFFSET_CAPTURE)) {
            preg_match("/(\r?\nendstream)/", $content, $endStream, PREG_OFFSET_CAPTURE);
            $streamOffset = $startStream[0][1] + strlen($startStream[0][0]);
            $streamLength = $endStream[0][1] - $streamOffset;
            $stream = substr($content, $streamOffset, $streamLength);
            return $stream;
        }
        return false;
	}

	/**
     * Return the length of the object body
	 * 
	 * @return int The length of the object body in bytes
     */
	public function length(): int
	{
		return strlen($this->get());
	}

	/**
     * Return the object body
	 * 
	 * @return string The object body
     */
	public function get(): string
	{
		$address = $this->address;
		$content = '';
		if (preg_match("/(?:\bxref|\btrailer|\bstartxref)(\d{7}\b)/", $address, $matches)) {
			$address = str_replace($matches[1], '', $address);
		}
		$content .= "\n" . $address . "\n";

        if (!empty($this->dictionary) || !empty($this->stream)) {
            if (!empty($this->dictionary)) {
				if (isset($this->dictionary['Length'])) {
					$this->dictionary['Length'] = strlen($this->stream);
				}
                $content .= StringTools::unescape($this->dictionary->getString());
            }
            if (!empty($this->stream)) {
                $content .= "stream\n";
                $content .= $this->stream;
                $content .= "\r\nendstream";
            }
        }
        else {
            $content .= $this->value;
        }
        
		if (!in_array($address, ['trailer', 'xref', 'startxref'])) {
            $content .= "\nendobj";
        }
		elseif ($address == 'startxref') {
			$content .= "\n%%EOF";
		}

		if(isset($this->paddingLength) && $this->paddingLength > 0) {
			$content .= str_repeat(' ', $this->paddingLength);
		}
		return $content;
	}
}