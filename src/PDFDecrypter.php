<?php
declare(strict_types=1);

namespace Abcde1410\PDFDecrypter;

use Abcde1410\PDFDecrypter\Cryptography\Decrypter;
use Abcde1410\PDFDecrypter\PDF\DocumentObject;
use Abcde1410\PDFDecrypter\PDF\Document;
use Abcde1410\PDFDecrypter\PDF\Password;
use Abcde1410\PDFDecrypter\PDF\HexadecimalString;
use Abcde1410\PDFDecrypter\PDF\LiteralString;
use Abcde1410\PDFDecrypter\PDF\Dictionary;
use Abcde1410\PDFDecrypter\Exceptions\PDFDecrypterException;

class PDFDecrypter
{   
    /**
	 * Encrypted document object
	 * @protected
	 */
    protected Document $encryptedDocument;

    /**
	 * Decrypted document object
	 * @protected
	 */
    protected Document $decryptedDocument;

    /**
	 * Decrypter object
	 * @protected
	 */
    protected Decrypter $decrypter;

    /**
	 * Dokument password object
	 * @protected
	 */
    protected Password $password;

    /**
	 * Name of the file to be decrypted
	 * @protected
	 */
    protected string $filename;

    public function __construct(string $filename = null, string $password = null)
    {
        if ($filename) {
            $this->openFile($filename);
        }
        if ($password) {
            $this->setPassword($password);
        }
    }

    /**
     * Open file with the given filename
     * 
     * @param string $filename
     * 
     * @return self
     * 
     * @throws PDFDecrypterException when given filename i empty
     * @throws PDFDecrypterException when file with given filename does not exist
     */
    public function openFile(string $filename): self
    {
        if (empty($filename)) {
            throw new PDFDecrypterException('Given filename is empty');
        } 
        if (!file_exists($filename)) {
            throw new PDFDecrypterException('File with given filename does not exist');
        }
        $this->filename = basename($filename);
        $this->setDocumentContent(file_get_contents($filename));
        return $this;
    }

    /**
     * Create a new encrypted document object and store the document content in it
     * 
     * @param string $content
     * 
     * @return self
     */
    public function setDocumentContent(string $content): self
    {
        $this->encryptedDocument = new Document($content);
        return $this;
    }

    /**
     * Set document authentication password
     * 
     * @param string $password
     * 
     * @return self
     */
    public function setPassword(string $password): self
    {
        $this->password = new Password($password);
        return $this;
    }

    /**
     * Check if the given authentication password is correct 
     * 
     * @param string $password
     * 
     * @return bool True if the given authentication password is correct, false if it is not
     * 
     * @throws PDFDecrypterException when file authentication password has not been set
     */
    public function verifyPassword(string $password = null): bool
    {
        if (empty($this->password) || !($this->password instanceof Password)) {
            if ($password) {
                $this->setPassword($password);
            }
            else {
                throw new PDFDecrypterException('Password has not been set');
            }
        }
        if (empty($this->decrypter) || !($this->decrypter instanceof Decrypter)) {
            $this->initializeDecrypter();
        }
        return $this->decrypter->verifyPassword();
    }

    /**
     * Return the decrypted document body
     * 
     * @return string Decrypted document body
     */
    public function get(): string
    {
        try {
            return $this->decrypt();
        } catch (PDFDecrypterException $e) {
            throw $e;
        }
    }

    /**
     * Send decrypted document body to the browser and show it
     */
    public function show(string $filename = ''): void
    {
        if (empty($filename)) {
            $filename = $this->filename ?? 'document.pdf';
        }
        $document = $this->get();
        header('Content-type:application/pdf'); 
        header('Content-Disposition: inline; filename="' . $filename . '"');
        echo $document;
    }

    /**
     * Send decrypted document body to the browser and download it
     */
    public function download(string $filename = ''): void
    {
        if (empty($filename)) {
            $filename = $this->filename ?? 'document.pdf';
        }
        $document = $this->get();
        header('Content-type:application/pdf'); 
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo $document;
    }

    /**
     * Decrypt the given encrypted content
     * 
     * @return string Decrypted content
     * 
     * @throws PDFDecrypterException when the document content for decryption has not been set
     * @throws PDFDecrypterException when the authorization password has not been set or when the provided authorization password is incorrect
     */
    protected function decrypt(): string
    {
        if (empty($this->encryptedDocument)) {
            throw new PDFDecrypterException('The document for decryption has not been set');
        }
        elseif(empty($this->password) || !$this->password->verified) {
            if (!$this->verifyPassword()) {
                throw new PDFDecrypterException('Unable to decrypt due to an incorrect authorization password provided');
            }
            $this->password->verify();
        }

        if (!($this->decrypter instanceof Decrypter)) {
            $this->initializeDecrypter();
        }

        if (!$this->decrypter->prepare()) {
            throw new PDFDecrypterException('There was an error hindering the decryption of the document');
        }
        $this->decryptedDocument = new Document();
        $this->decryptedDocument->addXRefObjects($this->encryptedDocument->XRefObjects);
        $this->decryptedDocument->addHeader($this->encryptedDocument->header);

        foreach ($this->encryptedDocument->objects as $key => $object) {
            if ($key == $this->encryptedDocument->encryptObject) {
                continue;
            }

            $decryptedObject = new DocumentObject();
            $decryptedObject->setAddress($object->address);

            if (!empty($this->encryptedDocument->metadataObject) 
            && $key == $this->encryptedDocument->metadataObject
            && $this->decrypter->getEncryptDataElement('EncryptMetadata') == false) {
                $decryptedObject->setDictionary($object->dictionary)
                                ->setStream($object->stream);
                $this->decryptedDocument->addObject($decryptedObject);
                continue;
            }

            if ($this->encryptedDocument->XRefObjects['type'] == 'table') {
                if (in_array($key, $this->encryptedDocument->XRefObjects['key'])) {
                    $decryptedObject->setValue($object->value);
                    $this->decryptedDocument->addObject($decryptedObject);
                    continue;
                }
                elseif ($key == 'trailer') {
                    unset($object->dictionary['Encrypt']);
                    $decryptedObject->setDictionary($object->dictionary);
                    $this->decryptedDocument->addObject($decryptedObject);
                    continue;
                }
            }
            elseif ($this->encryptedDocument->XRefObjects['type'] == 'object') {
                if (in_array($key, $this->encryptedDocument->XRefObjects['key'])) {
                    unset($object->dictionary['Encrypt']);
                    $decryptedObject->setDictionary($object->dictionary)
                                    ->setStream($object->stream);
                    $this->decryptedDocument->addObject($decryptedObject);
                    continue;
                } 
            }
        
            if (!empty($object->dictionary) || !empty($object->stream)) {
                if (!empty($object->dictionary)) {
                    $decryptedDictionary = $this->decryptDictionary($object->dictionary->getArray(), $object->address);
                    $decryptedObject->setDictionary($decryptedDictionary);
                }
                if (!empty($object->stream)) {
                    if (!$value = $this->decrypter->decrypt($object->stream, $object->address)) {
                        $value = $object->stream;
                    }
                    $decryptedObject->setStream($value);
                } 
            }
            else {
                $decryptedObject->setValue($object->value);
            }
            $this->decryptedDocument->addObject($decryptedObject);
            unset($this->encryptedDocument->objects[$key]);
        }
        return $this->decryptedDocument->create(); 
    }

    /**
     * Initialize instance of the Decrypter class
     */
    protected function initializeDecrypter(): void
    {
        $this->decrypter = new Decrypter($this->encryptedDocument->findEncryptDictionary(), $this->password);
    }

    /**
     * Decrypt the given dictionary object
     * 
     * @param array $dictionary Encrypted dictionary intended for decryption
     * @param string $objectAddress Address of the object containing the given dictionary
     * 
     * @return Dictionary Dictionary object containing decrypted data
     */
    protected function decryptDictionary(array $dictionary, string $objectAddress): Dictionary
    {
        $this->decryptDict($dictionary, $objectAddress);
        return new Dictionary($dictionary);
    }

    /**
     * Decrypt the given dictionary
     * This method is recursive
     * 
     * @param array &$dict Reference to the dictionary intended for decryption
     * @param string $objectAddress Address of the object containing the given dictionary
     */
    protected function decryptDict(array &$dict, string $objectAddress): void
    {
        $currentArrayKey = null;
        foreach ($dict as $k => $v) {
            if (is_array($v)) {
                if (!array_is_list($v)) {
                    $currentArrayKey = $k;
                }
                $this->decryptDict($v, $objectAddress);
            }
            else {
                if ($currentArrayKey != 'ID') {
                    if ($v instanceof LiteralString) {
                        if (!$value = $this->decrypter->decrypt($v->content, $objectAddress)) {
                            $value = null;
                        }
                        $v->set($value);
                    }
                    elseif ($v instanceof HexadecimalString) {
                        $v->set($this->decrypter->decrypt($v->bin(), $objectAddress));
                    }
                }
                else {
                    $currentArrayKey = null;
                }
            }
        }
    }
}