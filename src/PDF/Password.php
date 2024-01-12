<?php
declare(strict_types=1);

namespace Abcde1410\PDFDecrypter\PDF;

use Abcde1410\PDFDecrypter\Exceptions\PDFDecrypterException;

class Password extends StringObject
{
    /**
	 * True if password is verified, false if is not
	 * @public
	 */
    public bool $verified;

    /**
	 * Type of the password, 1 = owner password, 2 = user password
	 * @public
	 */
    public int $type;
    
    /**
     * Set the password
     *
     * @param string $content Password
     */
    public function set(string $content): void 
    {
        $this->verified = false;
        $this->type = 0;
        parent::set($content);
    }

    /**
     * Set the password type
     *
     * @param string $type Password type
     * 
     * @throws PDFDecrypterException when the given password type is unrecognized
     */
    public function setType(string $type): void 
    {
        if ($type == 'owner') {
            $this->type = 1;
        }
        elseif ($type == 'user') {
            $this->type = 2;
        }
        else {
            throw new PDFDecrypterException('Unrecognized password type');
        }
    }

    /**
     * Set the password as verified
     */
    public function verify(): void
    {
        $this->verified = true;
    }
}