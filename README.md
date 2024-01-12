# PDFDecrypter

PDF Decrypter is a standalone PHP library allowing quick and convenient decryption and permanently removing password from PDF files.

## Version Status

This library is currently in the beta phase. This means that it is undergoing testing, and some features may not be fully supported yet. Below is a list of limitations and considerations:

- Stream filters other than the FlateDecode filter are not yet supported.
- Predictor algorithms other than the PNG Up Algorithm are not yet supported.
- Hint tables for linearized files are not yet supported, so the file is no longer linearized after decryption.

## System Requirements

- PHP version 8.1 or later

## Installation

Install the latest version with:

```shell
composer require abcde1410/pdfdecrypter
```

## Usage

```php
Use Abcde1410\PDFDecrypter\PDFDecrypter;
```

This library may throw `PDFDecrypterException` in certain situations. It is recommended to use it within a try-catch block to handle these exceptions gracefully.



1. To set the content of the encrypted document from the file:
```php
$decrypter = new PDFDecrypter('path/to/file.pdf');
```
OR
```php
$decrypter = new PDFDecrypter();
$decrypter->openFile('path/to/file.pdf');
```

2. If you would like to set the content of the encrypted document that has been previously loaded into memory, use the `setDocumentContent()` method:
```php
$decrypter = new PDFDecrypter();
$decrypter->setDocumentContent($encryptedContent);
```

3. Set encrypted document authentication password:
```php
$decrypter->setPassword('document_password');
```

4. To verify the correctness of the provided password, use the `verifyPassword()` method. You can omit this step if you are confident that the provided password is correct. The method returns true if the password is correct and false otherwise.
```php
if ($decrypter->verifyPassword() === true) {
    // password is correct
}
else {
    // password is incorrect
}
```

5. You can get, show or download the decrypted file. 
To get decryption result as a plaintext use `get()` method:
```php
$decryptedFile = $decrypter->get();
```
To show the decrypted file in the browser use `show()` method:
```php
$decrypter->show();
```
To download the decrypted file use `download()` method:
```php
$decrypted->download();
```

## License
This library is under the [MIT License](https://github.com/abcde1410/pdfdecrypter/blob/master/LICENSE).