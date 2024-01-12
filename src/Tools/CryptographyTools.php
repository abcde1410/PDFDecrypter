<?php
declare(strict_types=1);

namespace Abcde1410\PDFDecrypter\Tools;

use Abcde1410\PDFDecrypter\PDF\StringObject;

class CryptographyTools
{
    public const ENCRYPTION_PADDING_STRING = "\x28\xBF\x4E\x5E\x4E\x75\x8A\x41\x64\x00\x4E\x56\xFF\xFA\x01\x08\x2E\x2E\x00\xB6\xD0\x68\x3E\x80\x2F\x0C\xA9\xFE\x64\x53\x69\x7A";
    
    /**
     * Remove padding string from password
     * 
     * @param StringObject $password Password to truncate
     * 
     * @return StringObject Truncated password
     */
    public static function truncatePassword(StringObject $password): StringObject
    {
        $paddingStartPosition = strrpos($password->content, substr(self::ENCRYPTION_PADDING_STRING, 0, 1));
        $paddingLength = $password->length() - $paddingStartPosition;
        $password->set(str_replace(substr(self::ENCRYPTION_PADDING_STRING, 0, $paddingLength), '', $password->content));
        return $password;
    }

    /**
     * Convert permissions string to the its binary representation
     * 
     * @param string $permissions Permissions string
     * 
     * @return string Binary representation of the permissions string
     */
    public static function convertPermissionsToBinary(string $permissions): string
    {
        $permissions = sprintf("%u", $permissions & 0xFFFFFFFF);
        $binaryPermissions = sprintf('%032b', $permissions);
		$result = chr(bindec(substr($binaryPermissions, 24, 8)));
		$result .= chr(bindec(substr($binaryPermissions, 16, 8)));
		$result .= chr(bindec(substr($binaryPermissions, 8, 8)));
		$result .= chr(bindec(substr($binaryPermissions, 0, 8)));
		return $result;
	}
}