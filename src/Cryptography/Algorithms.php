<?php
declare(strict_types=1);

namespace Abcde1410\PDFDecrypter\Cryptography;

use Abcde1410\PDFDecrypter\PDF\StringObject;

class Algorithms
{
    /**
     * RC4 encryption/decryption algorithm
     *
     * @param string $key Encryption key
     * @param string $input String to encrypt or decrypt
     * 
     * @return StringObject Object containing encrypted or decrypted string
     */
    public static function RC4(string $key, string $input): StringObject
    {
        $s = range(0, 255);
        $j = 0;
    
        for ($i = 0; $i < 256; $i++) {
            $j = ($j + $s[$i] + ord($key[$i % strlen($key)])) % 256;
            [$s[$i], $s[$j]] = [$s[$j], $s[$i]];
        }
    
        $i = 0;
        $j = 0;
        $result = '';
    
        for ($y = 0; $y < strlen($input); $y++) {
            $i = ($i + 1) % 256;
            $j = ($j + $s[$i]) % 256;
    
            [$s[$i], $s[$j]] = [$s[$j], $s[$i]];
            $result .= $input[$y] ^ chr($s[($s[$i] + $s[$j]) % 256]);
        }
        return new StringObject($result);
    }

    /**
     * AES encryption
     *
     * @param string $key Encryption key
     * @param string $plaintext Plaintext to encrypt
     * @param string $iv Initialization vector
     * 
     * @return StringObject Object containing encrypted string
     */
    public static function AESEncrypt(string $key, string $plaintext, string $iv): StringObject
    {
		$algorithm = strlen($key) == 16 ? 'aes-128-cbc' : 'aes-256-cbc';
        $ciphertext = openssl_encrypt($plaintext, $algorithm, $key, OPENSSL_RAW_DATA|OPENSSL_ZERO_PADDING, $iv);
		return new StringObject($ciphertext);
    }

    /**
     * AES decryption
     *
     * @param string $key Decryption key
     * @param string $ciphertext Ciphertext to decrypt
     * @param string $iv Initialization vector
     * 
     * @return StringObject|false Object containing decrypted string or false if decryption was unsuccessful
     */
    public static function AESDecrypt(string $key, string $ciphertext, string $iv = null): StringObject|bool
    {
		$algorithm = strlen($key) == 16 ? 'aes-128-cbc' : 'aes-256-cbc';
        $iv = !$iv ? str_repeat("\x00", openssl_cipher_iv_length($algorithm)) : $iv;
        if (!$plaintext = openssl_decrypt($ciphertext, $algorithm, $key, OPENSSL_RAW_DATA, $iv)) {
            $plaintext = openssl_decrypt($ciphertext, $algorithm, $key, OPENSSL_RAW_DATA|OPENSSL_ZERO_PADDING, $iv);
        }
		return $plaintext ? new StringObject($plaintext) : false;
    }
}