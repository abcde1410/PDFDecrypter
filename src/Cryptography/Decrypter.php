<?php
declare(strict_types=1);

namespace Abcde1410\PDFDecrypter\Cryptography;

use Abcde1410\PDFDecrypter\PDF\Password;
use Abcde1410\PDFDecrypter\PDF\StringObject;
use Abcde1410\PDFDecrypter\PDF\Dictionary;
use Abcde1410\PDFDecrypter\Tools\CryptographyTools;
use Abcde1410\PDFDecrypter\Tools\StringTools;
use Abcde1410\PDFDecrypter\Exceptions\PDFDecrypterException;

class Decrypter
{
    /**
	 * Document encryption properties
	 * @protected
	 */
    protected Dictionary $encryptData;

    /**
	 * Document authentication password
	 * @protected
	 */
    protected Password $password;

    /**
	 * Computed decryption key
	 * @protected
	 */
    protected string $decryptionKey;

    public function __construct(Dictionary $encryptData, Password $password)
    {
        $this->setPassword($password);
        $this->setEncryptData($encryptData);
    }

     /**
     * Set document authentication password
     *
     * @param Password $password Instance of Password object containing document authentication password
     */
    private function setPassword(Password $password): void
    {
        $this->password = $password;
    }

    /**
     * Save the dictionary containing document encryption properties into the $this->encryptData property
     */
    private function setEncryptData(Dictionary $encryptData): void
    {
        $this->encryptData = $encryptData;
        $this->encryptData['EncryptMetadata'] = !empty($this->encryptData['EncryptMetadata']) ? filter_var($this->encryptData['EncryptMetadata'], FILTER_VALIDATE_BOOLEAN) : true;
    }

    /**
     * Check if the provided password is correct
     *
     * @return bool True if password is correct, false if not
     * 
     * @throws PDFDecrypterException when authentication password has not been set
     */
    public function verifyPassword(): bool
    {
        if (empty($this->password)) {
            throw new PDFDecrypterException('Password has not been set');
        }

        if ($this->encryptData['R'] == 2) {
            $userPasswordRestoredFromOwnerPassword = CryptographyTools::truncatePassword(Algorithms::RC4($this->computeDecryptionKey($this->password, true), $this->encryptData['O']->content));
            $key = $this->computeDecryptionKey($userPasswordRestoredFromOwnerPassword);
            if ($isOwnerPasswordCorrect = Algorithms::RC4($key, CryptographyTools::ENCRYPTION_PADDING_STRING)->content == $this->encryptData['U']->content) {
                $this->decryptionKey = $key;
            }
            else {
                $key = $this->computeDecryptionKey($this->password);
                if ($isUserPasswordCorrect = Algorithms::RC4($key, CryptographyTools::ENCRYPTION_PADDING_STRING)->content == $this->encryptData['U']->content) {
                    $this->decryptionKey = $key;
                }
            }
        }
        elseif ($this->encryptData['R'] <= 4) {
            $key = $this->computeDecryptionKey($this->password, true);
            $decrypt = $this->encryptData['O'];
            for ($i = 19; $i >= 0; $i--) {
                $newKey = '';
                for ($j = 0; $j < strlen($key); $j++) {
                    $newKey .= chr(ord($key[$j]) ^ $i);
                }
                $decrypt = Algorithms::RC4($newKey, $decrypt->content);
            }
            $userPasswordRestoredFromOwnerPassword = CryptographyTools::truncatePassword($decrypt);
            if ($isOwnerPasswordCorrect = $this->authenticateOwnerPassword($userPasswordRestoredFromOwnerPassword)) {
                $this->decryptionKey = $this->computeDecryptionKey($userPasswordRestoredFromOwnerPassword);
            }
            else {
                if ($isUserPasswordCorrect = $this->authenticateOwnerPassword($this->password)) {
                    $this->decryptionKey = $this->computeDecryptionKey($this->password);
                }
            }
        }
        elseif ($this->encryptData['R'] == 5) {
            if (!$isOwnerPasswordCorrect = $this->computeHash($this->password->content.$this->getOwnerValidationSalt().$this->encryptData['U']->content) == substr($this->encryptData['O']->content, 0, 32)) {
                $isUserPasswordCorrect = $this->computeHash($this->password->content.$this->getUserValidationSalt()) == substr($this->encryptData['U']->content, 0, 32);
            }
        }
        elseif ($this->encryptData['R'] >= 6) {
            if (!$isOwnerPasswordCorrect = $this->computeHash($this->password->content.$this->getOwnerValidationSalt().substr($this->encryptData['U']->content, 0, 48), true) == substr($this->encryptData['O']->content, 0, 32)) {
                $isUserPasswordCorrect = $this->computeHash($this->password->content.$this->getUserValidationSalt()) == substr($this->encryptData['U']->content, 0, 32);
            }
        }
        if ($isOwnerPasswordCorrect) {
            $this->password->setType('owner');
        }
        elseif ($isUserPasswordCorrect) {
            $this->password->setType('user');
        }
        return $isOwnerPasswordCorrect || $isUserPasswordCorrect;
    }

    /**
     * Generate the decryption key required for document decryption when $this->encryptData['R'] <= 4
     * ISO 32000-2 FDIS Algorithm 2
     * 
     * @param StringObject $password Password used to compute decryption key
     * @param bool $isOwnerPassword true if the hash is computed for a password suspected to be the owner's password
     * 
     * @return string Computed decryption key
     */
    private function computeDecryptionKey(StringObject $password, bool $isOwnerPassword = false): string
    {
        $lengthBytes = $this->encryptData['Length'] / 8;
        $hashEntry = substr($password->content.CryptographyTools::ENCRYPTION_PADDING_STRING, 0, 32);
        if (!$isOwnerPassword) {
            $hashEntry .= $this->encryptData['O']->content;
            $hashEntry .= CryptographyTools::convertPermissionsToBinary($this->encryptData['P']);
            $id = ctype_xdigit($this->encryptData['ID']->content) ? hex2bin($this->encryptData['ID']->content) : $this->encryptData['ID']->content;
            $hashEntry .= $id;

            if ($this->encryptData['R'] >= 4 && !$this->encryptData['EncryptMetadata']) {
                $hashEntry .= "\xFF\xFF\xFF\xFF";
            }
        }
        $hash = StringTools::md5Hex($hashEntry);

        if ($this->encryptData['R'] >= 3) {
            for ($i = 0; $i < 50; $i++) {
                $hash = StringTools::md5Hex(substr($hash, 0, $lengthBytes));
            }
        }

        if ($this->encryptData['R'] == 2) {
            $decryptionKey = substr($hash, 0, 5);
        }
        elseif ($this->encryptData['R'] >= 3) {
            $decryptionKey = substr($hash, 0, $lengthBytes);
        }
        return $decryptionKey;
    }

    /**
     * Authenticate given password when $this->encryptData['R'] > 2 and $this->encryptData['R'] <= 4
     * and given password is suspected to be the owner's password
     * ISO 32000-2 FDIS Algorithm 7
     * 
     * @param StringObject $password Password to authenticate
     *
     * @return bool True if password is correct, false if not
     */
    private function authenticateOwnerPassword(StringObject $password): bool
    {
        $key = $this->computeDecryptionKey($password);
        $id = ctype_xdigit($this->encryptData['ID']->content) ? hex2bin($this->encryptData['ID']->content) : $this->encryptData['ID']->content;
        $hash = StringTools::md5Hex(CryptographyTools::ENCRYPTION_PADDING_STRING.$id);
        $encrypt = Algorithms::RC4($key, $hash);
        for ($i = 1; $i <= 19; $i++) {
            $newKey = '';
            for ($j = 0; $j < strlen($key); $j++) {
                $newKey .= chr(ord($key[$j]) ^ $i);
            }
            $encrypt = Algorithms::RC4($newKey, $encrypt->content);
        }
        $encrypt->addToContent(str_repeat("\x00", 16));

        return substr($encrypt->content, 0, 16) == substr($this->encryptData['U']->content, 0, 16);
    }

    /**
     * Compute the decryption hash when $this->encryptData['R'] >= 6
     * ISO 32000-2 FDIS Algorithm 2.B
     * 
     * @param string $input Initialization key
     * @param bool $isOwnerPassword true if the hash is computed for a password suspected to be the owner's password
     * 
     * @return string Decryption hash
     */
    private function computeHash(string $input, bool $isOwnerPassword = false): string
    {
        $K = hash('sha256', $input, true);
        if ($this->encryptData['R'] >= 6) {
            $i = 0;
            $U = $isOwnerPassword ? substr($this->encryptData['U']->content, 0, 48) : '';
            
            while (true) {
                $K1 = str_repeat($this->password->content.$K.$U, 64);
                $E = Algorithms::AESEncrypt(substr($K, 0, 16), $K1, substr($K, 16, 16))->content;
                
                $int = 0;
                for ($j = 0; $j < 16; $j++) {
                    $int += ord($E[$j]);
                }

                switch ($int%3) {
                    case 0:
                        $hash = 'sha256';
                        break;
                    case 1:
                        $hash = 'sha384';
                        break;
                    case 2:
                        $hash = 'sha512';
                        break;
                }
                $K = hash($hash, $E, true);
                $i++;
                if ($i >= 64 && ord(substr($E, -1)) <= $i - 32) {
                    break;
                }
            }
        }
        return substr($K, 0, 32);
    }

    /**
     * Extract user validation salt when $this->encryptData['R'] > 4
     * 
     * @return string|false Value of user validation salt or false when $this->encryptData['R'] < 4
     */
    private function getUserValidationSalt(): string|false
    {
        if ($this->encryptData['R'] > 4) {
            return substr($this->encryptData['U']->content, 32, 8);
        }
        return false;
    }

    /**
     * Extract user key salt when $this->encryptData['R'] > 4
     * 
     * @return string|false Value of user key salt or false when $this->encryptData['R'] < 4
     */
    private function getUserKeySalt(): string|false
    {
        if ($this->encryptData['R'] > 4) {
            return substr($this->encryptData['U']->content, 40, 8);
        }
        return false;
    }

    /**
     * Extract owner validation salt when $this->encryptData['R'] > 4
     * 
     * @return string|false Value of owner validation salt or false when $this->encryptData['R'] < 4
     */
    private function getOwnerValidationSalt(): string|false
    {
        if ($this->encryptData['R'] > 4) {
            return substr($this->encryptData['O']->content, 32, 8);
        }
        return false;
    }

    /**
     * Extract owner key salt when $this->encryptData['R'] > 4
     * 
     * @return string|false Value of owner key salt or false when $this->encryptData['R'] < 4
     */
    private function getOwnerKeySalt(): string|false
    {
        if ($this->encryptData['R'] > 4) {
            return substr($this->encryptData['O']->content, 40, 8);
        }
        return false;
    }

    /**
     * Prepare the decrypter object by setting necessary data that may not have been established in previous steps
     * 
     * @throws PDFDecrypterException if the correct authentication password has not been entered yet
     * 
     * @return  bool true if the preparation succeeded, false if it did not
     */
    public function prepare(): bool
    {
        if (empty($this->password) || !$this->password->verified || !$this->password->type) {
            throw new PDFDecrypterException('The decrypter preparation cannot be started because the correct password for the file has not been entered');
        }

        $this->retrieveCFMValue();

        if (empty($this->decryptionKey) && $this->encryptData['V'] == 5) {
            $this->completeEncryptionKey();
        }

        if (!empty($this->decryptionKey)) {
            return true;
        }
        return false;
    }

    /**
     * Retrieve the CFM value if the document encryption object does not contain it or contains an incorrect value
     */
    private function retrieveCFMValue(): void
    {
        if (!isset($this->encryptData['CF']['StdCF']['CFM']) || !in_array($this->encryptData['CF']['StdCF']['CFM'], ['V2', 'AESV2', 'AESV3'])) {
            if ($this->encryptData['V'] == 5 || $this->encryptData['Length'] == 256) {
                $this->setEncryptionMethod('AESV3');
            }
            elseif ($this->encryptData['Length'] == 40) {
                $this->setEncryptionMethod('V2');
            }
            else {
                $this->setEncryptionMethod();
            }
        }
    }

    /**
     * Set the document encryption method
     * 
     * @param string $method Encryption method value
     * 
     * @throws PDFDecrypterException if the provided encryption method is incorrect
     */
    private function setEncryptionMethod(string $method = ''): void
    {
        if (empty($method) || in_array($method, ['V2', 'AESV2', 'AESV3'])) {
            $this->encryptData['CF'] = ['StdCF' => ['CFM' => $method]];
        }
        else {
            throw new PDFDecrypterException('The provided encryption method is incorrect');
        }
    }

    /**
     * Complete the encryption key when $this->encryptData['V'] == 5
     * because when $this->encryptData['V'] < 5 the encryption key is
     * set during password verification
     */
    private function completeEncryptionKey(): void
    {
        if (empty($this->decryptionKey) && $this->encryptData['V'] == 5) {
            if ($this->password->type == 1) {
                if ($this->encryptData['R'] == 5) {
                    $keyHash = hash('sha256', $this->password->content.$this->getOwnerKeySalt().$this->encryptData['U']->content, true);
                }
                else {
                    $keyHash = $this->computeHash($this->password->content.$this->getOwnerKeySalt().substr($this->encryptData['U']->content, 0, 48), true);
                }
                $key = Algorithms::AESDecrypt($keyHash, $this->encryptData['OE']);
            }
            elseif ($this->password->type == 2) {
                if ($this->encryptData['R'] == 5) {
                    $keyHash = hash('sha256', $this->password->content.$this->getUserKeySalt(), true);
                }
                else {
                    $keyHash = $this->computeHash($this->password->content.$this->getUserKeySalt());
                }
                $key = Algorithms::AESDecrypt($keyHash, $this->encryptData['UE']->content);    
            }
            $this->decryptionKey = $key->content;
        }
    }

    /**
     * Get an element from the $this->encryptData dictionary
     * 
     * @param string $key Key of the searched element
     * 
     * @return string|bool value of the element or false if the element does not exist
     */
    public function getEncryptDataElement(string $key): string|bool
    {
        if (isset($this->encryptData[$key])) {
            return $this->encryptData[$key];
        }
        return false;
    }

    /**
     * Get an element from the $this->encryptData dictionary
     * ISO 32000-2 FDIS Algorithm 1
     * 
     * @param int $objectNumber Number of object to compute decryption key for
     * @param int $generationNumber Generation number of object to compute decryption key for
     * @param bool $isAES True if the object is encrypted using the AES algorithm
     * 
     * @return string Object decryption key
     */
    private function computeObjectKey(int $objectNumber, int $generationNumber, bool $isAES = false): string
    {
        $salt = '';
        if ($isAES) {
            $salt = "\x73\x41\x6C\x54";
        } 
		$objectKey = $this->decryptionKey.pack('VX', $objectNumber).pack('VXX', $generationNumber).$salt;
		$objectKey = substr(StringTools::md5Hex($objectKey), 0, (($this->encryptData['Length'] / 8) + 5));
		$objectKey = substr($objectKey, 0, 16);
		return $objectKey;
	}
    
    /**
     * Decrypt given ciphertext
     * 
     * @param string $ciphertext Ciphertext to decrypt
     * @param string $objectAddress Address of the object containing given ciphertext
     * 
     * @return string|bool Decrypted plaintext or false if the decryption was unsuccessful
     */
    public function decrypt(string $ciphertext, string $objectAddress): string|bool
    {
        $key = $this->decryptionKey;

        if ($this->encryptData['V'] <= 4) {
            preg_match_all("/(\d+)/", $objectAddress, $objectNumbers);
            list($objectNumber, $generationNumber) = $objectNumbers[0];
            $isAES = $this->encryptData['CF']['StdCF']['CFM'] != 'V2' ? true : false;
            $key = $this->computeObjectKey((int) $objectNumber, (int) $generationNumber, $isAES);

            if (empty($this->encryptData['CF']['StdCF']['CFM'])) {
                if (Algorithms::AESDecrypt($key, substr($ciphertext, 16), substr($ciphertext, 0, 16))) {
                    $this->setEncryptionMethod('AESV2');
                }
                else {
                    $key = $this->computeObjectKey((int) $objectNumber, (int) $generationNumber, false);
                    $this->setEncryptionMethod('V2');
                }
            } 
        }

        if ($this->encryptData['CF']['StdCF']['CFM'] == 'V2') {
            $plaintext = Algorithms::RC4($key, $ciphertext);
        }
        else {
            $plaintext = Algorithms::AESDecrypt($key, substr($ciphertext, 16), substr($ciphertext, 0, 16));
        }
        if ($plaintext instanceof StringObject) {
            $plaintext = $plaintext->content;
        } 
        return $plaintext;
    }
}