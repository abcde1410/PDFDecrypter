<?php
declare(strict_types=1);

namespace Abcde1410\PDFDecrypter\PDF;

use Abcde1410\PDFDecrypter\Encoding\FilterFactory;
use Abcde1410\PDFDecrypter\Encoding\PredictorFactory;
use Abcde1410\PDFDecrypter\Tools\StringTools;
use Abcde1410\PDFDecrypter\Exceptions\PDFDecrypterException;

class Document
{
    const XREF_OBJECT_PADDING_LENGTH = 50;
    const LINEARIZE_OBJECT_PADDING_LENGTH = 10;

    /**
	 * An array containing objects that constitute the document
	 * @public
	 */
    public array $objects;

    /**
	 * An array containing objects that constitute the document
	 * @public
	 */
    public array $XRefObjects = array();

    /**
	 * Key of the encrypt object in $this->objects array
	 * @public
	 */
    public string $encryptObject;
    
    /**
	 * Key of the metadata object in $this->objects array
	 * @public
	 */
    public string $metadataObject;

    /**
	 * Document content
	 * @public
	 */
    public string $content;

    /**
	 * Document header
	 * @public
	 */
    public string $header;

    public function __construct(string $content = '')
    {
        if (!empty($content)) {
            $this->set($content);
        }
    }

    /**
     * Set document
     *
     * @param string $content content of the document
     */
    public function set(string $content): void
    {
        $content = trim($content);
        $this->content = $content;
        $this->findObjects();
        $this->findHeader();
        $this->setProperties();
        unset($this->content);
    }

    /**
     * Split document content to objects that constitute the document
     */
    private function findObjects(): void
    {
        $objects = $this->findObjectsOffsets();
        foreach ($objects as $address => $offset) {
            if (next($objects)) {
                $object = substr($this->content, $offset, current($objects) - $offset);
            }
            else {
                $lastObjectEndOffset = strpos($this->content, '%%EOF', $offset);
                $object = substr($this->content, $offset, $lastObjectEndOffset - $offset + 5);
            }
            $object = new DocumentObject($address, $object, $offset);
            $this->objects[DocumentObject::getNumber($address)] = $object;
        }
    }

    /**
     * Find the offset of each object in the document
     * 
     * @return array Array containing objects offsets
     */
    private function findObjectsOffsets(): array
    {
        preg_match_all("/\b\d+\s+\d+\s+obj\b|\b(?<!\/)xref\b|\btrailer\b|\bstartxref\b/i", $this->content, $matches, PREG_OFFSET_CAPTURE);
        $objects = [];
        foreach($matches[0] as $match) {
            $address = $match[0];
            if (!in_array($address, ['xref', 'trailer', 'startxref'])) {
                $address = StringTools::normalizeObjectAddress($match[0]);
            }
            if (array_key_exists($address, $objects)) {
                $keys = array_keys($objects);
                $offset = array_search($address, $keys);
                $array1 = array_slice($objects, 0, $offset + 1);
                $array2 = array_slice($objects, $offset + 1);
                list($msec, $sec) = explode(' ', microtime());
                $array1[$address.substr($sec, -1).substr($msec, 2, 6)] = $array1[$address];
                unset($array1[$address]);
                $objects = array_merge($array1, $array2);
            }
            $objects[$address] = $match[1];
        }
        return $objects;
    }

    /**
     * Set document properties
     */
    private function setProperties(): void
    {
        if (isset($this->objects['xref'])) {
            $this->XRefObjects = ['type' => 'table',
                                  'key' => [$this->objects['xref']->address]];
        }
        else {
            $this->findXRefObjects((int) $this->objects['startxref']->value);
        }

        $XRefObject = $this->XRefObjects['type'] == 'table' ? $this->objects['trailer'] : $this->objects[end($this->XRefObjects['key'])];
        $this->encryptObject = DocumentObject::getNumber(StringTools::normalizeObjectAddress($XRefObject->dictionary['Encrypt']));
        $rootObject = DocumentObject::getNumber(StringTools::normalizeObjectAddress($XRefObject->dictionary['Root']));
        if (isset($this->objects[$rootObject]->dictionary['Metadata'])) {
            $this->metadataObject = StringTools::normalizeObjectAddress($this->objects[$rootObject]->dictionary['Metadata']);
        }
    }

    /**
     * Find header in the document content
     */
    private function findHeader(): void
    {
        $header = trim(substr($this->content, 0, reset($this->objects)->offset));
        $this->addHeader($header);
    }

    /**
     * Add header to the document
     * 
     * @param string Header content
     */
    public function addHeader(string $header): void
    {
        $this->header = $header;
    }

    /**
     * Find all cross-reference objects in the document
     * This method is recursive
     * 
     * @param int $offset Offset of the current cross-reference object
     */
    private function findXRefObjects(int $offset): void
    {
        $XRefObject = $this->findObject($offset);
        if (!isset($this->XRefObjects['type'])) {
            $this->XRefObjects['type'] = 'object';
        }
        $this->XRefObjects['key'][] = DocumentObject::getNumber($XRefObject->address);
        if (isset($XRefObject->dictionary['Prev'])) {
            $this->findXRefObjects((int) $XRefObject->dictionary['Prev']);
        }
    }

    /**
     * Create stream for cross-reference object
     * 
     * @param DocumentObject $XRefObject Object containing cross-reference data
     * 
     * @return string Created cross-reference stream
     */
    private function createXRefStream(DocumentObject $XRefObject): string
    {
        $numberOfBytesPerEntry = $XRefObject->dictionary['DecodeParms']['Columns'] + 1;
        $predictor = new PredictorFactory((int) $XRefObject->dictionary['DecodeParms']['Columns'], (int) $XRefObject->dictionary['DecodeParms']['Predictor']);
        $filter = new FilterFactory($XRefObject->dictionary['Filter']);
        $entry = $filter->decode($XRefObject->stream);
        $entry = $predictor->decode($entry);
        $entries = str_split($entry, $numberOfBytesPerEntry);
        $i = isset($XRefObject->dictionary['Index'][0]) ? $XRefObject->dictionary['Index'][0] : 0;

        foreach ($entries as $entry) {
            if (ord($entry[1]) === 0 || ord($entry[1]) === 2) {
                $updatedEntries[] = $entry;
            }
            elseif (!isset($this->objects[$i])) {
                $newEntry = $entry[0];
                for ($j = 0; $j < array_sum($XRefObject->dictionary['W']); $j++) {
                    $newEntry .= pack('C', 0);
                }
                $updatedEntries[] = $newEntry;
            }
            elseif (ord($entry[1]) === 1) {
                $string = $entry[0];
                for ($j = 1; $j < 1 + (int) $XRefObject->dictionary['W'][0]; $j++) {
                    $string .= $entry[$j];
                }
                
                $padding = $this->objects[$i]->offset > $XRefObject->offset ? self::XREF_OBJECT_PADDING_LENGTH : 0;
                
                if ($XRefObject->dictionary['W'][1] == 1) {
                    $packCode = 'C';
                }
                elseif ($XRefObject->dictionary['W'][1] == 2) {
                    $packCode = 'n';
                }
                elseif ($XRefObject->dictionary['W'][1] > 2 && $XRefObject->dictionary['W'][1] <= 4) {
                    $packCode = 'N';
                }
                elseif ($XRefObject->dictionary['W'][1] > 4 && $XRefObject->dictionary['W'][1] <= 8) {
                    $packCode = 'J';
                }

                $offset = pack($packCode, $this->objects[$i]->offset + $padding);
                $offset = substr($offset, strlen($offset) - $XRefObject->dictionary['W'][1]);

                $string .= $offset;


                for ($j = 1 + $XRefObject->dictionary['W'][0] + $XRefObject->dictionary['W'][1]; $j < 1 + array_sum($XRefObject->dictionary['W']); $j++) {
                    $string .= $entry[$j];
                }
                $updatedEntries[] = $string;
            }
            $i++;
        }
        $updatedEntries = $predictor->encode(implode('', $updatedEntries));
        return $filter->encode($updatedEntries);
    }

    /**
     * Add the type and keys of the document's cross-reference objects
     * 
     * @param array $objects 
     * 
     * @throws PDFDecrypterException when the type of the given cross-reference objects is incorrect
     */
    public function addXRefObjects(array $objects): void
    {
        if (!isset($objects['type']) || !in_array($objects['type'], ['table', 'object'])) {
            throw new PDFDecrypterException("Type of the given XRef objects is incorrect");
        }
        $this->XRefObjects = $objects;
    }

    /**
     * Return the document encrypt dictionary
     * 
     * @return Dictionary Document encrypt dictionary
     */
    public function findEncryptDictionary(): Dictionary
    {
        $encryptDictionary = $this->objects[$this->encryptObject]->dictionary;
        $XRefObject = $this->XRefObjects['type'] == 'table' ? $this->objects['trailer'] : $this->objects[end($this->XRefObjects['key'])];
        $encryptDictionary['ID'] = $XRefObject->dictionary['ID'][0];
        return $encryptDictionary;
    }

    /**
     * Find object by given id
     * 
     * @param string|int $idObject Name, address or offset of searched object
     * 
     * @return DocumentObject Searched object
     * 
     * @throws PDFDecrypterException when the object could not be found
     */
    protected function findObject(string|int $idObject): DocumentObject
    {
        $address = null;
        if (is_int($idObject) || preg_match("/\b\d+\b/", $idObject)) {
            preg_match("/\b(\d+\s+\d+\s+obj)\b/i", $this->content, $matches, PREG_OFFSET_CAPTURE, $idObject);
            if (isset($matches[0][1]) && $matches[0][1] === $idObject) {
                $address = $matches[0][0];
            }
            $type = 1;
        }
        elseif (is_string($idObject) && preg_match("/\b\d+\s+\d+\s*obj/i", $idObject)) {
            $address = $idObject;
            $type = 2;
        }
        elseif (is_string($idObject) && preg_match_all("/\/$idObject\s*(\d+\s+\d+\s*R)/i", $this->content, $matches, PREG_SET_ORDER)) {
            $lastOccurrence = end($matches);
            $address = end($lastOccurrence);
            $type = 3;
        }
        
        $address = $address ? StringTools::normalizeObjectAddress($address) : $address;

        if (!isset($this->objects[DocumentObject::getNumber($address)])) {
            switch ($type) {
                case 1:
                    $suffix = 'at the offset '.$idObject;
                    break;
                case 2:
                    $suffix = 'with the address ' .$idObject;
                    break;
                case 3:
                    $suffix = 'with the name ' .$idObject;
                    break;
            }
            throw new PDFDecrypterException('Failed to locate object ' . $suffix);
        }

        return $this->objects[DocumentObject::getNumber($address)];
    }

    /**
     * Add an object to the document
     * 
     * @param DocumentObject $object Object to add to the document
     */
    public function addObject(DocumentObject $object): void
    {
        if (empty($this->objects)) {
            $offset = strlen($this->header) + 1;
        }
        else {
            $lastObject = end($this->objects);
            $offset = $lastObject->offset + $lastObject->length();
        }
        $object->setOffset($offset);
        $this->objects[DocumentObject::getNumber($object->address)] = $object;
    }

    /**
     * Override the object with the given key
     * 
     * @param string $key The key of the object to override
     * @param DocumentObject The overriding object
     */
    public function overrideObject(string $key, DocumentObject $replace): void
    {
        $object = $this->objects[$key];
        $this->objects[$key] = $replace;

        if ($object->length() != $replace->length()) {
            foreach ($this->objects as $k => $v) {
                if ($v->offset > $replace->offset) {
                    $this->objects[$k]->offset = $v->offset + $replace->length() - $object->length();
                }
            }
        }
    }

    /**
     * Return the content of the document
     * 
     * @return string The document content as a string
     */
    public function get(): string
    {
        $content = $this->header;
        foreach ($this->objects as $object) {
            $content .= $object->get();
        }
        $content .= "\n";
        return $content;
    }

    /**
     * Return the length of the document
     * 
     * @return int Length of the document
     */
    public function length(): int
    {
        return strlen($this->get());
    }

    /**
     * Create the document by prepare new content of special objects and replacing the old ones
     * 
     * @return string The document content as a string
     */
    public function create(): string
    {
        $objectArrayKeys = array_keys($this->objects);
        $offsetDiff = 0;
        if ($isLinearized = isset(reset($this->objects)->dictionary['Linearized'])) {
            $object = $this->objects[$objectArrayKeys[0]];
            $newLinearizeObject = new DocumentObject();
            $newLinearizeObject->setAddress($object->address)
                               ->setOffset($object->offset)
                               ->setDictionary($object->dictionary)
                               ->setPadding(self::LINEARIZE_OBJECT_PADDING_LENGTH);
            $offsetDiff += $newLinearizeObject->length() - $object->length();
            $this->overrideObject((string) $objectArrayKeys[0], $newLinearizeObject);
        }
        
        if ($this->XRefObjects['type'] == 'object') {
            foreach ($this->XRefObjects['key'] as $key) {
                unset($newXRefObject);
                do {
                    $object = isset($newXRefObject) ? $newXRefObject : $this->objects[$key];
                    $newXRefObject = new DocumentObject();
                    $stream = $this->createXRefStream($object);
                    $dictionary = $object->dictionary;
                    $dictionary['Length'] = strlen($stream);
                    if (isset($dictionary['Prev'])) {
                        $dictionary['Prev'] = $this->objects[current($this->XRefObjects['key'])]->offset + self::XREF_OBJECT_PADDING_LENGTH;
                    }
                    $newXRefObject->setAddress($object->address)
                                ->setOffset($object->offset)
                                ->setDictionary($dictionary)
                                ->setStream($stream);
                    
                    if ($isLastXRefObject = ($key != array_keys($this->objects)[count($this->objects) - 2] && $newXRefObject->length() <= $object->length() + self::XREF_OBJECT_PADDING_LENGTH)) {
                        $newXRefObject->setPadding(self::XREF_OBJECT_PADDING_LENGTH + $object->length() - $newXRefObject->length());
                    }

                    $this->overrideObject((string) $key, $newXRefObject);

                    if ($isLastXRefObject) {
                        break;
                    }

                } while ($newXRefObject->length() > $object->length() + self::XREF_OBJECT_PADDING_LENGTH);
            }
        }

        $object = $this->objects['startxref'];
        $newStartXRefObject = new DocumentObject();
        $newStartXRefObject->setAddress($object->address)
                            ->setOffset($object->offset)
                            ->setValue($this->objects[$this->XRefObjects['key'][0]]->offset);
        $this->overrideObject('startxref', $newStartXRefObject);

        if ($isLinearized) {
            $object = $this->objects[array_key_first($this->objects)];
            $dictionary = $object->dictionary;
            $dictionary['L'] = $this->length();
            $newLinearizedObject = new DocumentObject();
            $newLinearizedObject->setAddress($object->address)
                                ->setOffset($object->offset)
                                ->setDictionary($dictionary);

            $newLinearizedObject->setPadding($object->length() - $newLinearizedObject->length());
            $this->overrideObject((string) array_key_first($this->objects), $newLinearizedObject);
        }

        return $this->get();
    }
}